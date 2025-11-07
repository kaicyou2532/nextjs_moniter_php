<?php
// Web ルート外のディレクトリへのパス
require __DIR__ . '/auth.php';

define('BASE_DIR', realpath(__DIR__ . '/../'));
define('NEXT_DIR', BASE_DIR . '/next-app');
define('PID_FILE', BASE_DIR . '/pids/nextjs.pid');
define('LOG_FILE', BASE_DIR . '/logs/nextjs.log');

$GitUrl = getenv('GITURL');

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ストリーミングレスポンス用のヘッダー設定
if (isset($data['stream']) && $data['stream'] === true) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // バッファがある場合のみ削除
    while (ob_get_level()) {
        ob_end_flush();
    }
    
    ob_implicit_flush(true);
} else {
    header('Content-Type: text/plain; charset=UTF-8');
}

/**
 * 現在のプロセスが起動中かどうかを判定
 */
function isRunning(): bool {
    if (!file_exists(PID_FILE)) {
        return false;
    }
    $pid = (int)trim(file_get_contents(PID_FILE));
    return $pid > 0 && posix_kill($pid, 0);
}

/**
 * nginx を再起動
 */
function restartNginx(): void {
    // Dockerコンテナ内では supervisorctl を使用
    if (file_exists('/usr/bin/supervisorctl')) {
        passthru('supervisorctl restart nginx 2>&1', $code);
        echo ($code === 0)
            ? "[OK]リバースプロキシを再起動しました\n"
            : "[ERR]リバースプロキシの再起動に失敗しました (exit $code)\n";
    } else {
        // 従来のsystemctl（ホスト環境用）
        passthru('systemctl restart nginx 2>&1', $code);
        echo ($code === 0)
            ? "[OK]リバースプロキシを再起動しました\n"
            : "[ERR]リバースプロキシの再起動に失敗しました (exit $code)\n";
    }
}

/**
 * Git リポジトリを origin/main から pull
 */
function GitPull(): bool {
    global $GitUrl;
    
    // 環境変数とGit設定
    putenv('HOME=/root');
    
    // Gitconfig権限修正
    passthru('mkdir -p /root/.config/git 2>/dev/null || true');
    passthru('touch /root/.gitconfig && chmod 644 /root/.gitconfig 2>/dev/null || true');
    
    // Git設定を追加（安全なディレクトリとして設定）
    passthru('git config --global --add safe.directory /var/www/html/next-app 2>/dev/null || true');
    passthru('git config --global --add safe.directory "*" 2>/dev/null || true');
    
    // Next.jsディレクトリが存在しない場合はクローン
    if (!is_dir(NEXT_DIR) || !is_dir(NEXT_DIR . '/.git')) {
        // 既存ディレクトリを完全削除
        if (is_dir(NEXT_DIR)) {
            passthru('rm -rf ' . escapeshellarg(NEXT_DIR), $code);
        }
        
        // GitURLまたはデフォルトURLを使用
        $repoUrl = !empty($GitUrl) ? $GitUrl : 'https://github.com/AIM-SC/next-website.git';
        
        // 新しくクローン（rootユーザーで実行）
        chdir(BASE_DIR);
        
        echo "[INFO]リポジトリをクローン中...\n";
        passthru(sprintf(
            'HOME=/root git clone %s next-app 2>&1',
            escapeshellarg($repoUrl)
        ), $code);
        
        if ($code !== 0) {
            echo "[ERR]リポジトリのクローンに失敗しました (exit $code)\n";
            return false;
        }
        
        // 所有権とディレクトリ権限を修正
        passthru('chmod -R 755 ' . escapeshellarg(NEXT_DIR), $chmodCode);
        passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR), $chownCode);
        
        echo "[OK]リポジトリをクローンしました\n";
        return true;
    }
    
    // 既存リポジトリの場合はpull
    chdir(NEXT_DIR);
    
    // 所有権とディレクトリ権限を修正
    passthru('chmod -R 755 ' . escapeshellarg(NEXT_DIR), $chmodCode);
    passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR), $chownCode);

    // 環境変数で GITURL が指定されていれば origin を上書き
    if (!empty($GitUrl)) {
        passthru(sprintf(
            'HOME=/root git remote set-url origin %s 2>&1',
            escapeshellarg($GitUrl)
        ), $code);
        if ($code !== 0) {
            echo "[ERR]リモートURL設定失敗 (exit $code)\n";
            return false;
        }
    }

    // main ブランチを pull
    echo "[INFO]最新版を取得中...\n";
    passthru('HOME=/root git pull origin main 2>&1', $code);
    
    if ($code === 0) {
        echo "[OK]最新版の取得が完了しました\n";
        // pull後も所有権を修正
        passthru('chmod -R 755 ' . escapeshellarg(NEXT_DIR));
        passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR));
        return true;
    } else {
        echo "[ERR]git pull失敗 (exit $code)\n";
        return false;
    }
}

/**
 * ファイル末尾を取得
 */
function tail(string $file, int $lines = 10): string {
    $fp        = fopen($file, 'r');
    $pos       = -2;
    $data      = '';
    $lineCount = 0;
    fseek($fp, $pos, SEEK_END);

    while ($lineCount < $lines) {
        $char = fgetc($fp);
        if ($char === "\n") {
            $lineCount++;
        }
        $data = $char . $data;
        if (ftell($fp) <= 1) {
            rewind($fp);
            break;
        }
        fseek($fp, --$pos, SEEK_END);
    }

    fclose($fp);
    return $data;
}

/**
 * ポート3000を使用しているプロセスを停止
 */
function killPort3000Processes(): void {
    echo "=== ポート3000使用中のプロセスを停止 ===\n";
    flush();
    
    // ポート3000を使用しているプロセスを検索
    $output = shell_exec('lsof -ti:3000 2>/dev/null || netstat -tlnp 2>/dev/null | grep :3000 | awk \'{print $7}\' | cut -d/ -f1 2>/dev/null || ss -tlnp 2>/dev/null | grep :3000 | grep -o "pid=[0-9]*" | cut -d= -f2 2>/dev/null');
    
    if (!empty($output)) {
        $pids = array_filter(explode("\n", trim($output)));
        foreach ($pids as $pid) {
            $pid = trim($pid);
            if (is_numeric($pid) && $pid > 0) {
                echo "[INFO] ポート3000使用中のプロセス PID: $pid を停止します\n";
                posix_kill((int)$pid, SIGTERM);
                sleep(1);
                // SIGTERMで停止しない場合はSIGKILL
                if (posix_kill((int)$pid, 0)) {
                    posix_kill((int)$pid, SIGKILL);
                    echo "[INFO] 強制停止しました (PID: $pid)\n";
                } else {
                    echo "[OK] プロセスを停止しました (PID: $pid)\n";
                }
            }
        }
    } else {
        echo "[INFO] ポート3000を使用中のプロセスはありません\n";
    }
    
    // Nodeプロセスも確認して停止
    passthru('pkill -f "node.*3000" 2>/dev/null || true');
    passthru('pkill -f "next.*start" 2>/dev/null || true');
    passthru('pkill -f "next.*dev" 2>/dev/null || true');
    echo "[OK] Node.js関連プロセスをクリーンアップしました\n";
    
    flush();
}

/**
 * コマンドをバックグラウンドで実行してPIDを保存
 */
function startInBackground(string $command, string $workingDir = null): bool {
    if ($workingDir) {
        chdir($workingDir);
    }
    
    // バックグラウンドで実行
    $fullCommand = "nohup $command > " . LOG_FILE . " 2>&1 & echo $!";
    $pid = trim(shell_exec($fullCommand));
    
    if ($pid && is_numeric($pid)) {
        file_put_contents(PID_FILE, $pid);
        return true;
    }
    
    return false;
}

/**
 * コマンドをリアルタイム実行してログを出力
 */
function executeWithLiveOutput(string $command, string $workingDir = null): int {
    if ($workingDir) {
        chdir($workingDir);
    }
    
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    $process = proc_open($command, $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        fclose($pipes[0]); // stdin を閉じる
        
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        
        while (true) {
            $stdout = fgets($pipes[1]);
            $stderr = fgets($pipes[2]);
            
            if ($stdout !== false) {
                echo $stdout;
                flush();
            }
            
            if ($stderr !== false) {
                echo $stderr;
                flush();
            }
            
            $status = proc_get_status($process);
            if (!$status['running']) {
                // プロセス終了後の残りの出力を読み取り
                while (($stdout = fgets($pipes[1])) !== false) {
                    echo $stdout;
                    flush();
                }
                while (($stderr = fgets($pipes[2])) !== false) {
                    echo $stderr;
                    flush();
                }
                break;
            }
            
            usleep(100000); // 0.1秒待機
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $exitCode = proc_close($process);
        return $exitCode;
    }
    
    return -1;
}

// ストリーミング対応かどうか
$isStreaming = isset($data['stream']) && $data['stream'] === true;

// アクション判定
switch ($action) {
    case 'build':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        if ($isStreaming) {
            // ポート3000のプロセスを自動停止
            killPort3000Processes();
            
            echo "=== npm権限修正 ===\n";
            flush();
            passthru('chown -R www-data:www-data /var/www/.npm 2>/dev/null || true');
            passthru('mkdir -p /tmp/.npm && chown -R www-data:www-data /tmp/.npm');
            
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('HOME=/root npm install --cache /tmp/.npm 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
            
            if ($code === 0) {
                echo "\n=== ビルド開始 ===\n";
                flush();
                $code = executeWithLiveOutput('HOME=/root npm run build 2>&1', NEXT_DIR);
                echo ($code === 0)
                    ? "\n[OK] ビルド完了\n"
                    : "\n[ERR] ビルド失敗 (exit $code)\n";
            }
            
            if ($code === 0) {
                echo "\n=== サーバー起動（ポート3000） ===\n";
                flush();
                
                // バックグラウンドでNext.jsを起動
                if (startInBackground('HOME=/root PORT=3000 npm run start', NEXT_DIR)) {
                    echo "[OK] Next.jsアプリをポート3000で起動しました\n";
                    echo "[INFO] http://localhost:3000でアクセス可能です\n";
                } else {
                    echo "[ERR] バックグラウンド起動に失敗しました\n";
                }
            }
        } else {
            chdir(NEXT_DIR);
            passthru('npm install 2>&1', $code);
            echo ($code === 0)
                ? "[OK] 依存関係インストール完了\n"
                : "[ERR] 依存関係インストール失敗 (exit $code)\n";

            passthru('npm run build 2>&1', $code);
            echo ($code === 0)
                ? "[OK] ビルド完了\n"
                : "[ERR] ビルド失敗 (exit $code)\n";

            passthru('npm run start 2>&1', $code);
            echo ($code === 0)
                ? "[OK] スタート完了\n"
                : "[ERR] スタート失敗 (exit $code)\n";
        }
        break;

    case 'start':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        echo "=== Next.jsアプリ起動 ===\n";
        
        // ポート3000のプロセスを自動停止
        killPort3000Processes();
        
        // バックグラウンドでNext.jsを起動
        if (startInBackground('HOME=/root PORT=3000 npm run start', NEXT_DIR)) {
            $pid = trim(file_get_contents(PID_FILE));
            echo "[OK] 起動しました (PID: $pid)\n";
        } else {
            echo "[ERR] 起動に失敗しました\n";
        }
        break;

    case 'dev':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        if ($isStreaming) {
            // ポート3000のプロセスを自動停止
            killPort3000Processes();
            
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('HOME=/root npm install --cache /tmp/.npm 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
            
            if ($code === 0) {
                echo "\n=== 開発サーバー起動（ポート3000） ===\n";
                flush();
                
                // バックグラウンドで開発サーバを起動
                if (startInBackground('HOME=/root PORT=3000 npm run dev', NEXT_DIR)) {
                    echo "[OK] 開発サーバーをポート3000で起動しました\n";
                } else {
                    echo "[ERR] 開発サーバー起動に失敗しました\n";
                }
            }
        } else {
            chdir(NEXT_DIR);
            // 依存関係インストール（必要に応じて）
            passthru('npm install 2>&1', $code);
            echo ($code === 0)
                ? "[OK] 依存関係インストール完了\n"
                : "[ERR] 依存関係インストール失敗 (exit $code)\n";

            // 開発用サーバをバックグラウンドで起動
            $cmd = sprintf(
                'nohup npm run dev > %s 2>&1 & echo $!',
                escapeshellarg(LOG_FILE)
            );
            $pid = shell_exec($cmd);
            file_put_contents(PID_FILE, trim($pid));
            echo "[OK] 開発サーバを起動しました (PID: " . trim($pid) . ")\n";
        }
        break;

    case 'stop':
        echo "=== Next.jsアプリ停止 ===\n";
        
        // PIDファイルから停止
        if (isRunning()) {
            $pid = (int)trim(file_get_contents(PID_FILE));
            if ($pid > 0 && posix_kill($pid, SIGTERM)) {
                unlink(PID_FILE);
                echo "[OK]Next.jsアプリを停止しました (PID: $pid)\n";
            } else {
                echo "[ERR]プロセス停止に失敗しました\n";
            }
        } else {
            echo "[INFO]PIDファイルにプロセスが見つかりません\n";
        }
        
        // ポート3000を使用している全プロセスを停止
        killPort3000Processes();
        
        echo "[OK]停止処理が完了しました\n";
        break;

    case 'restart':
        // 停止
        if (isRunning()) {
            $pid = (int)trim(file_get_contents(PID_FILE));
            posix_kill($pid, SIGTERM);
            unlink(PID_FILE);
            echo "[OK] 停止しました (PID: $pid)\n";
        }
        // 起動
        chdir(NEXT_DIR);
        $cmd = sprintf(
            'nohup npm run start > %s 2>&1 & echo $!',
            escapeshellarg(LOG_FILE)
        );
        $pid = shell_exec($cmd);
        file_put_contents(PID_FILE, trim($pid));
        echo "[OK] WEBアプリを再起動しました (PID: " . trim($pid) . ")\n";
        break;

    case 'status':
        if (isRunning()) {
            $pid = (int)trim(file_get_contents(PID_FILE));
            echo "[RUNNING] PID: $pid\n";
        } else {
            echo "[STOPPED]\n";
        }
        echo "\n-- 最新ログ --\n";
        echo file_exists(LOG_FILE)
            ? tail(LOG_FILE, 20)
            : "(ログファイルがありません)\n";
        break;

    case 'nginx':
        // nginx 再起動
        restartNginx();
        break;

    case 'install':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        if ($isStreaming) {
            echo "=== npm権限修正 ===\n";
            flush();
            passthru('mkdir -p /tmp/.npm && chown -R www-data:www-data /tmp/.npm');
            
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('HOME=/root npm install --cache /tmp/.npm 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
        }
        break;    

    case 'Renewal':
        echo "=== GitHubから最新版を取得 ===\n";
        if (GitPull()) {
            echo "[OK]最新版の取得が完了しました\n";
        } else {
            echo "[ERR]最新版の取得に失敗しました\n";
        }
        break;

    default:
        echo "不正なアクションです\n";
        break;
}
