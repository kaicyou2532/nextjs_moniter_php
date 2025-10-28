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
    ob_implicit_flush(true);
    ob_end_flush();
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
    chdir(NEXT_DIR);

    // 環境変数で GITURL が指定されていれば origin を上書き
    if (!empty($GitUrl)) {
        passthru(sprintf(
            'git remote set-url origin %s 2>&1',
            escapeshellarg($GitUrl)
        ), $code);
        if ($code !== 0) {
            return false;
        }
    }

    // main ブランチを pull
    passthru('git pull origin main 2>&1', $code);
    return ($code === 0);
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
        if ($isStreaming) {
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('npm install 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
            
            if ($code === 0) {
                echo "\n=== ビルド開始 ===\n";
                flush();
                $code = executeWithLiveOutput('npm run build 2>&1', NEXT_DIR);
                echo ($code === 0)
                    ? "\n[OK] ビルド完了\n"
                    : "\n[ERR] ビルド失敗 (exit $code)\n";
            }
            
            if ($code === 0) {
                echo "\n=== サーバー起動 ===\n";
                flush();
                $code = executeWithLiveOutput('npm run start 2>&1', NEXT_DIR);
                echo ($code === 0)
                    ? "\n[OK] スタート完了\n"
                    : "\n[ERR] スタート失敗 (exit $code)\n";
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
        if (isRunning()) {
            echo "[WARN] すでに起動中です\n";
            break;
        }
        chdir(NEXT_DIR);
        // nohup でバックグラウンド起動し、PID を保存
        $cmd = sprintf(
            'nohup npm run start > %s 2>&1 & echo $!',
            escapeshellarg(LOG_FILE)
        );
        $pid = shell_exec($cmd);
        file_put_contents(PID_FILE, trim($pid));
        echo "[OK] 起動しました (PID: " . trim($pid) . ")\n";
        break;

    case 'dev':
        if ($isStreaming) {
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('npm install 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
            
            if ($code === 0) {
                // 開発用サーバをバックグラウンドで起動
                $cmd = sprintf(
                    'nohup npm run dev > %s 2>&1 & echo $!',
                    escapeshellarg(LOG_FILE)
                );
                $pid = shell_exec($cmd);
                file_put_contents(PID_FILE, trim($pid));
                echo "[OK] 開発サーバを起動しました (PID: " . trim($pid) . ")\n";
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
        if (!isRunning()) {
            echo "[WARN] プロセスが見つかりません\n";
            break;
        }
        $pid = (int)trim(file_get_contents(PID_FILE));
        posix_kill($pid, SIGTERM);
        unlink(PID_FILE);
        echo "[OK] 停止しました (PID: $pid)\n";
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
        if ($isStreaming) {
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            $code = executeWithLiveOutput('npm install 2>&1', NEXT_DIR);
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
        } else {
            chdir(NEXT_DIR);
            // npm install を実行
            passthru('npm install 2>&1', $code);
            echo ($code === 0)
                ? "[OK] 依存関係インストール完了\n"
                : "[ERR] 依存関係インストール失敗 (exit $code)\n";
        }
        break;    

    case 'Renewal':
        // Git リポジトリから最新版を取得
        if (!GitPull()) {
            echo "[WARN] GitHub リポジトリから最新版を取得できませんでした。\n";
            break;
        }
        echo "[OK] リポジトリを更新しました\n";
        break;

    default:
        echo "不正なアクションです\n";
        break;
}
