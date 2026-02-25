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
    echo "[INFO]リバースプロキシ(nginx)を再起動中...\n";
    
    // Docker環境（supervisor使用）
    if (file_exists('/usr/bin/supervisorctl')) {
        // supervisorctl でnginx再起動（rootユーザーで実行）
        echo "[INFO]supervisorctl経由でnginxを再起動...\n";
        
        // 再起動コマンド
        passthru('supervisorctl restart nginx 2>&1', $restartCode);
        
        if ($restartCode === 0) {
            echo "[OK]リバースプロキシを再起動しました\n";
            
            // 再起動後の状態確認
            sleep(2);
            echo "[INFO]nginx状態確認:\n";
            passthru('supervisorctl status nginx 2>&1');
            
            // ポート80の待ち受け確認
            echo "[INFO]ポート80の待ち受け確認:\n";
            passthru('ss -tlnp | grep :80 2>&1 || netstat -tlnp | grep :80 2>&1 || echo "ポート80で待ち受けしていません"');
        } else {
            echo "[WARN]supervisorctl restart が失敗しました。nginx プロセスを直接再起動します...\n";
            
            // 代替方法: nginx プロセスを直接操作
            passthru('pkill -HUP nginx 2>&1 || nginx -s reload 2>&1', $reloadCode);
            
            if ($reloadCode === 0) {
                echo "[OK]nginxを再読み込みしました\n";
            } else {
                echo "[ERR]nginxの再起動に失敗しました\n";
                
                // エラーログを表示
                echo "[INFO]nginxエラーログ:\n";
                passthru('tail -10 /var/log/nginx/error.log 2>/dev/null || tail -10 /var/log/supervisor/nginx*.log 2>/dev/null || echo "エラーログが見つかりません"');
            }
        }
    } else {
        // ホスト環境（systemctl使用）
        echo "[INFO]systemctlでnginxを再起動中...\n";
        passthru('systemctl restart nginx 2>&1', $code);
        echo ($code === 0)
            ? "[OK]リバースプロキシを再起動しました\n"
            : "[ERR]リバースプロキシの再起動に失敗しました (exit $code)\n";
    }
}

/**
 * nginx を停止
 */
function stopNginx(): void {
    echo "[INFO]リバースプロキシ(nginx)を停止中...\n";
    
    // supervisorctl経由でnginx停止
    if (file_exists('/usr/bin/supervisorctl')) {
        echo "[INFO]supervisorctlでnginxを停止中...\n";
        passthru('supervisorctl stop nginx 2>&1', $stopCode);
        
        if ($stopCode === 0) {
            echo "[OK]リバースプロキシ(nginx)を停止しました\n";
            
            // 停止後の状態確認
            echo "[INFO]nginx停止状態確認:\n";
            passthru('supervisorctl status nginx 2>&1');
        } else {
            echo "[ERR]リバースプロキシの停止に失敗しました (exit $stopCode)\n";
        }
    } else {
        // systemctl経由でnginx停止
        echo "[INFO]systemctlでnginxを停止中...\n";
        passthru('systemctl stop nginx 2>&1', $code);
        echo ($code === 0)
            ? "[OK]リバースプロキシ(nginx)を停止しました\n"
            : "[ERR]リバースプロキシの停止に失敗しました (exit $code)\n";
    }
    
    // ポート80の確認
    echo "[INFO]ポート80の状態確認:\n";
    passthru('netstat -tlnp | grep :80 2>&1 || echo "ポート80は使用されていません"');
}

/**
 * Git リポジトリを origin/main から pull(通常運用では不使用)
 */
function GitPull(): bool {
    global $GitUrl;
    
    // 環境変数とGit設定
    putenv('HOME=/root');
    putenv('GIT_CONFIG_GLOBAL=/root/.gitconfig');
    
    // Gitconfig権限修正
    passthru('mkdir -p /root/.config/git 2>/dev/null || true');
    passthru('mkdir -p /root/.ssh 2>/dev/null || true');
    passthru('touch /root/.gitconfig && chmod 644 /root/.gitconfig 2>/dev/null || true');
    
    // Git設定を追加（安全なディレクトリとして設定）
    passthru('git config --global --add safe.directory /var/www/html/next-app 2>/dev/null || true');
    passthru('git config --global --add safe.directory "*" 2>/dev/null || true');
    passthru('git config --global init.defaultBranch main 2>/dev/null || true');
    
    // プロセス権限を確認
    echo "[INFO]現在のユーザー: " . trim(shell_exec('whoami')) . "\n";
    echo "[INFO]作業ディレクトリ: " . getcwd() . "\n";
    
    // Next.jsディレクトリが存在しない場合はクローン
    if (!is_dir(NEXT_DIR) || !is_dir(NEXT_DIR . '/.git')) {
        // 既存ディレクトリを完全削除
        if (is_dir(NEXT_DIR)) {
            echo "[INFO]既存ディレクトリを削除中...\n";
            passthru('chmod -R 777 ' . escapeshellarg(NEXT_DIR) . ' 2>/dev/null || true');
            passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR) . ' 2>/dev/null || true');
            passthru('rm -rf ' . escapeshellarg(NEXT_DIR), $code);
            
            // 削除確認
            if (is_dir(NEXT_DIR)) {
                echo "[WARN]ディレクトリ削除に失敗。強制削除を試行中...\n";
                passthru('sudo rm -rf ' . escapeshellarg(NEXT_DIR) . ' 2>/dev/null || true');
            }
        }
        
        // 親ディレクトリの権限確認
        passthru('chmod 755 ' . escapeshellarg(BASE_DIR) . ' 2>/dev/null || true');
        passthru('chown root:root ' . escapeshellarg(BASE_DIR) . ' 2>/dev/null || true');
        
        // GitURLまたはデフォルトURLを使用
        $repoUrl = !empty($GitUrl) ? $GitUrl : 'https://github.com/AIM-SC/next-website.git';
        
        // 新しくクローン（rootユーザーで実行）
        chdir(BASE_DIR);
        
        echo "[INFO]リポジトリをクローン中...\n";
        passthru(sprintf(
            'HOME=/root GIT_CONFIG_GLOBAL=/root/.gitconfig git clone %s next-app 2>&1',
            escapeshellarg($repoUrl)
        ), $code);
        
        if ($code !== 0) {
            echo "[ERR]リポジトリのクローンに失敗しました (exit $code)\n";
            echo "[INFO]別の方法でクローンを試行中...\n";
            
            // 代替方法：wgetでダウンロード
            passthru('mkdir -p ' . escapeshellarg(NEXT_DIR));
            passthru(sprintf(
                'cd %s && curl -L https://github.com/AIM-SC/next-website/archive/refs/heads/main.zip -o main.zip && unzip -q main.zip && mv next-website-main/* . && rm -rf next-website-main main.zip 2>&1',
                escapeshellarg(NEXT_DIR)
            ), $altCode);
            
            if ($altCode === 0) {
                echo "[OK]アーカイブダウンロードでリポジトリを取得しました\n";
                // Git初期化
                passthru(sprintf(
                    'cd %s && git init && git remote add origin %s 2>&1',
                    escapeshellarg(NEXT_DIR),
                    escapeshellarg($repoUrl)
                ));
                $code = 0; // 成功扱い
            } else {
                echo "[ERR]代替方法も失敗しました\n";
                return false;
            }
        }
        
        // 所有権とディレクトリ権限を修正
        passthru('chmod -R 755 ' . escapeshellarg(NEXT_DIR), $chmodCode);
        passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR), $chownCode);
        
        // Next.js環境変数ファイルを作成
        createNextJsEnvFile();
        
        echo "[OK]リポジトリをクローンしました\n";
        return true;
    }
    
    // 既存リポジトリの場合はpull
    chdir(NEXT_DIR);
    
    // .env.localファイルをバックアップ（存在する場合）
    $envFile = NEXT_DIR . '/.env.local';
    $envBackup = NEXT_DIR . '/.env.local.backup';
    if (file_exists($envFile)) {
        echo "[INFO]環境変数ファイルをバックアップ中...\n";
        copy($envFile, $envBackup);
    }
    
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

    // ローカル変更があるか確認
    echo "[INFO]ローカル変更を確認中...\n";
    exec('HOME=/root git status --porcelain 2>&1', $statusOutput, $statusCode);
    
    $hasChanges = !empty($statusOutput);
    if ($hasChanges) {
        echo "[WARN]ローカルに変更されたファイルがあります:\n";
        foreach ($statusOutput as $line) {
            echo "  $line\n";
        }
        
        echo "[INFO]ローカル変更を一時保存(stash)中...\n";
        passthru('HOME=/root git stash save "Auto-stash before deploy at ' . date('Y-m-d H:i:s') . '" 2>&1', $stashCode);
        
        if ($stashCode === 0) {
            echo "[OK]ローカル変更を一時保存しました\n";
        } else {
            echo "[WARN]stashに失敗しましたが続行します\n";
        }
    } else {
        echo "[INFO]ローカル変更はありません\n";
    }

    // main ブランチを pull
    echo "[INFO]最新版を取得中...\n";
    passthru('HOME=/root git pull origin main 2>&1', $code);
    
    if ($code === 0) {
        echo "[OK]最新版の取得が完了しました\n";
        
        // stashした変更がある場合の処理
        if ($hasChanges && $stashCode === 0) {
            echo "\n[INFO]一時保存したローカル変更の扱いについて:\n";
            echo "  - 変更を破棄: デプロイ後に最新版のみを使用\n";
            echo "  - 変更を復元: stash pop でローカル変更を復元（競合の可能性あり）\n";
            echo "[INFO]デフォルトでは変更を破棄します（本番環境推奨）\n";
            echo "[INFO]変更を確認するには: git stash list\n";
            echo "[INFO]変更を復元するには: git stash pop\n\n";
            
            // デフォルトでは変更を保持（破棄しない）
            // 必要に応じて手動で git stash drop できる
        }
        
        // 環境変数ファイルを復元
        if (file_exists($envBackup)) {
            echo "[INFO]環境変数ファイルを復元中...\n";
            copy($envBackup, $envFile);
            unlink($envBackup); // バックアップファイルを削除
            echo "[OK]環境変数ファイルを復元しました\n";
        } else if (!file_exists($envFile)) {
            echo "[INFO]環境変数ファイルが見つかりません。新規作成します\n";
            createNextJsEnvFile();
        }
        
        // pull後も所有権を修正
        passthru('chmod -R 755 ' . escapeshellarg(NEXT_DIR));
        passthru('chown -R root:root ' . escapeshellarg(NEXT_DIR));
        return true;
    } else {
        echo "[ERR]git pull失敗 (exit $code)\n";
        
        // pull失敗時はstashを復元
        if ($hasChanges && $stashCode === 0) {
            echo "[INFO]git pull失敗のためローカル変更を復元中...\n";
            passthru('HOME=/root git stash pop 2>&1', $popCode);
            if ($popCode === 0) {
                echo "[OK]ローカル変更を復元しました\n";
            } else {
                echo "[WARN]ローカル変更の復元に失敗しました\n";
                echo "[INFO]手動で復元するには: git stash pop\n";
            }
        }
        
        // 失敗時もバックアップから復元を試行
        if (file_exists($envBackup)) {
            echo "[INFO]git pull失敗のため環境変数ファイルを復元します\n";
            copy($envBackup, $envFile);
            unlink($envBackup);
        }
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
    
    // 複数の方法でポート3000を使用しているプロセスを検索
    $methods = [
        'lsof -ti:3000 2>/dev/null',
        'fuser 3000/tcp 2>/dev/null',
        'ss -tlnp 2>/dev/null | grep :3000 | grep -o "pid=[0-9]*" | cut -d= -f2',
        'netstat -tlnp 2>/dev/null | grep :3000 | awk \'{print $7}\' | cut -d/ -f1'
    ];
    
    $allPids = [];
    foreach ($methods as $cmd) {
        $output = shell_exec($cmd);
        if (!empty($output)) {
            $pids = array_filter(explode("\n", trim($output)));
            foreach ($pids as $pid) {
                $pid = trim($pid);
                if (is_numeric($pid) && $pid > 0) {
                    $allPids[$pid] = true;
                }
            }
        }
    }
    
    if (!empty($allPids)) {
        echo "[INFO] 検出されたプロセス: " . implode(', ', array_keys($allPids)) . "\n";
        
        foreach (array_keys($allPids) as $pid) {
            echo "[INFO] PID $pid を停止中...\n";
            
            // SIGTERM -> 待機 -> SIGKILL の順
            exec("kill -TERM $pid 2>/dev/null", $output, $code);
            sleep(1);
            
            // プロセスがまだ生きているか確認
            exec("kill -0 $pid 2>/dev/null", $output, $stillAlive);
            if ($stillAlive === 0) {
                echo "[WARN] PID $pid が停止しないため強制終了します\n";
                exec("kill -9 $pid 2>/dev/null");
                sleep(1);
            }
            
            // 最終確認
            exec("kill -0 $pid 2>/dev/null", $output, $finalCheck);
            if ($finalCheck === 0) {
                echo "[ERR] PID $pid の停止に失敗しました\n";
            } else {
                echo "[OK] PID $pid を停止しました\n";
            }
        }
    } else {
        echo "[INFO] ポート3000を使用中のプロセスはありません\n";
    }
    
    // 追加のクリーンアップ（プロセス名ベース）
    echo "[INFO] Next.js関連プロセスをクリーンアップ中...\n";
    passthru('pkill -9 -f "next start" 2>/dev/null || true');
    passthru('pkill -9 -f "next dev" 2>/dev/null || true');
    passthru('pkill -9 -f "node.*next" 2>/dev/null || true');
    
    // fuserでポート強制解放
    passthru('fuser -k 3000/tcp 2>/dev/null || true');
    
    sleep(2);
    
    // 最終確認
    exec("lsof -ti:3000 2>/dev/null", $finalOutput, $finalCode);
    if ($finalCode === 0 && !empty($finalOutput)) {
        echo "[WARN] ポート3000がまだ使用中です。最終強制終了を試みます...\n";
        passthru('lsof -ti:3000 | xargs kill -9 2>/dev/null || true');
        sleep(1);
    } else {
        echo "[OK] ポート3000は解放されました\n";
    }
    
    flush();
}

/**
 * Next.jsアプリケーションを起動
 */
function startNextJsApp(): bool {
    echo "\n=== Next.jsアプリケーション起動 ===\n";
    
    // Next.jsディレクトリの存在確認
    if (!is_dir(NEXT_DIR)) {
        echo "[ERR] Next.jsディレクトリが見つかりません: " . NEXT_DIR . "\n";
        return false;
    }
    
    // package.json存在確認
    if (!is_file(NEXT_DIR . '/package.json')) {
        echo "[ERR] package.jsonが見つかりません\n";
        return false;
    }
    
    // .nextビルドディレクトリの確認
    if (!is_dir(NEXT_DIR . '/.next')) {
        echo "[WARN] .nextディレクトリが見つかりません。ビルドが必要です\n";
        return false;
    }
    
    echo "[OK] Next.jsプロジェクト確認完了\n";
    echo "[INFO] 作業ディレクトリ: " . NEXT_DIR . "\n";
    
    // 既存プロセス停止
    killPort3000Processes();
    
    // まずnpm run startを試行
    if (startInBackground('HOME=/root PORT=3000 npm run start', NEXT_DIR)) {
        echo "[OK] npm run start で起動成功\n";
        $startMethod = "npm run start";
    } else {
        echo "[WARN] npm run start が失敗しました。代替方法を試行中...\n";
        
        // 代替方法1: node直接実行
        if (file_exists(NEXT_DIR . '/.next/standalone/server.js')) {
            echo "[INFO] standalone server.jsを検出。直接実行を試行...\n";
            if (startInBackground('HOME=/root PORT=3000 node .next/standalone/server.js', NEXT_DIR)) {
                echo "[OK] node直接実行で起動成功\n";
                $startMethod = "node direct";
            } else {
                echo "[ERR] node直接実行も失敗\n";
                return false;
            }
        } 
        // 代替方法2: next start直接実行
        else if (file_exists(NEXT_DIR . '/node_modules/.bin/next')) {
            echo "[INFO] nextコマンドを直接実行を試行...\n";
            if (startInBackground('HOME=/root PORT=3000 ./node_modules/.bin/next start', NEXT_DIR)) {
                echo "[OK] next start直接実行で起動成功\n";
                $startMethod = "next direct";
            } else {
                echo "[ERR] next start直接実行も失敗\n";
                return false;
            }
        } else {
            echo "[ERR] 代替起動方法が見つかりません\n";
            return false;
        }
    }
    
    echo "[OK] Next.jsアプリをポート3000で起動しました ($startMethod)\n";
    echo "[INFO] http://localhost:3000でアクセス可能です\n";
    echo "[INFO] nginx経由: http://localhost でアクセス可能です\n";
    
    // 起動確認
    sleep(5); // 起動に時間がかかる場合があるので少し長めに待つ
    $curlTest = shell_exec('curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 2>/dev/null');
    if ($curlTest == '200') {
        echo "[OK] Next.jsアプリが正常に応答しています (HTTP $curlTest)\n";
        return true;
    } else {
        echo "[WARN] Next.jsアプリの応答確認: HTTP $curlTest\n";
        
        // もう少し待ってから再確認
        echo "[INFO] 追加で5秒待機してから再確認...\n";
        sleep(5);
        $curlTest2 = shell_exec('curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 2>/dev/null');
        if ($curlTest2 == '200') {
            echo "[OK] Next.jsアプリが正常に応答しています (HTTP $curlTest2)\n";
            return true;
        } else {
            echo "[WARN] 再確認でも応答なし: HTTP $curlTest2\n";
            return false;
        }
    }
}

/**
 * Next.js環境変数ファイルを作成
 */
function createNextJsEnvFile() {
    $envPath = NEXT_DIR . "/.env.local";
    $envBackupPath = BASE_DIR . "/env-backup.txt";
    
    // 環境変数のデフォルト内容
    $defaultEnvContent = "# Next.js環境変数設定\n";
    $defaultEnvContent .= "# MicroCMSの設定\n";
    $defaultEnvContent .= "MICROCMS_SERVICE_DOMAIN=your-service-domain\n";
    $defaultEnvContent .= "MICROCMS_API_KEY=your-api-key\n";
    $defaultEnvContent .= "\n# その他の設定\n";
    $defaultEnvContent .= "NEXT_PUBLIC_BASE_URL=http://localhost:3000\n";
    
    // バックアップファイルから環境変数を復元、または デフォルト値を使用
    if (file_exists($envBackupPath)) {
        echo "[INFO]バックアップから環境変数を復元中...\n";
        $envContent = file_get_contents($envBackupPath);
        if (empty($envContent)) {
            echo "[WARN]バックアップファイルが空です。デフォルト設定を使用します。\n";
            $envContent = $defaultEnvContent;
        }
    } else {
        echo "[INFO]デフォルトの環境変数設定を使用します。\n";
        $envContent = $defaultEnvContent;
    }
    
    // ディレクトリの存在確認・作成
    $envDir = dirname($envPath);
    if (!is_dir($envDir)) {
        echo "[INFO]ディレクトリを作成中: $envDir\n";
        mkdir($envDir, 0755, true);
    }
    
    // ファイルを作成
    echo "[INFO]環境変数ファイルを作成中: $envPath\n";
    $result = file_put_contents($envPath, $envContent);
    
    if ($result !== false) {
        echo "[OK].env.localファイルを作成しました (サイズ: " . strlen($envContent) . " bytes)\n";
        
        // 環境変数をバックアップに保存
        if (file_put_contents($envBackupPath, $envContent) !== false) {
            echo "[INFO]バックアップファイルも更新しました\n";
        }
        
        // パーミッション設定
        if (chmod($envPath, 0644)) {
            echo "[INFO]ファイルパーミッションを設定しました (644)\n";
        }
        
        return true;
    } else {
        echo "[ERROR].env.localファイルの作成に失敗しました\n";
        echo "[DEBUG]書き込み先ディレクトリ: " . dirname($envPath) . "\n";
        echo "[DEBUG]ディレクトリ書き込み権限: " . (is_writable(dirname($envPath)) ? "OK" : "NG") . "\n";
        echo "[DEBUG]ディレクトリ存在確認: " . (is_dir(dirname($envPath)) ? "OK" : "NG") . "\n";
        return false;
    }
}

/**
 * Next.jsプロジェクト（package.json）が利用可能か確認し、無ければ取得を試行
 */
function ensureNextJsProjectAvailable(bool $autoCloneIfMissing = true): bool {
    if (is_dir(NEXT_DIR) && is_file(NEXT_DIR . '/package.json')) {
        return true;
    }

    echo "[WARN] Next.jsプロジェクトが未配置、または package.json がありません: " . NEXT_DIR . "\n";

    if (!$autoCloneIfMissing) {
        echo "[INFO] 先に『GitHubから最新版を取得』を実行してください\n";
        return false;
    }

    echo "[INFO] GITURL から Next.js プロジェクト取得を試行します...\n";
    $ok = GitPull();
    if (!$ok) {
        echo "[ERR] Next.jsプロジェクトの取得に失敗しました\n";
        return false;
    }

    if (!is_dir(NEXT_DIR) || !is_file(NEXT_DIR . '/package.json')) {
        echo "[ERR] 取得後も package.json が見つかりません。GITURL の内容を確認してください\n";
        return false;
    }

    return true;
}

/**
 * コマンドをバックグラウンドで実行してPIDを保存
 */
function startInBackground(string $command, string $workingDir = null): bool {
    if ($workingDir) {
        chdir($workingDir);
        echo "[INFO] 作業ディレクトリ: " . getcwd() . "\n";
    }
    
    // package.jsonのscriptを確認
    if (file_exists('package.json')) {
        $packageJson = json_decode(file_get_contents('package.json'), true);
        if (isset($packageJson['scripts']['start'])) {
            echo "[INFO] start script: " . $packageJson['scripts']['start'] . "\n";
        } else {
            echo "[WARN] package.jsonにstartスクリプトがありません\n";
        }
    }
    
    echo "[INFO] 実行コマンド: $command\n";
    
    // まず、npmコマンドが利用可能か確認
    $npmCheck = shell_exec('which npm 2>/dev/null');
    if (empty($npmCheck)) {
        echo "[ERR] npmコマンドが見つかりません\n";
        return false;
    }
    echo "[OK] npm: " . trim($npmCheck) . "\n";
    
    // Node.jsの確認
    $nodeCheck = shell_exec('which node 2>/dev/null');
    if (!empty($nodeCheck)) {
        echo "[OK] node: " . trim($nodeCheck) . "\n";
        $nodeVersion = shell_exec('node --version 2>/dev/null');
        echo "[INFO] Node.js version: " . trim($nodeVersion) . "\n";
    }
    
    // エラーログファイルを別途作成
    $errorLogFile = "/var/www/html/logs/npm-start-error.log";
    
    // バックグラウンドで実行（詳細ログ付き）
    $fullCommand = "cd " . escapeshellarg(getcwd()) . " && $command > " . LOG_FILE . " 2> $errorLogFile & echo $!";
    echo "[DEBUG] 実行する完全コマンド: $fullCommand\n";
    
    $pid = trim(shell_exec($fullCommand));
    
    echo "[INFO] 取得したPID: $pid\n";
    
    if ($pid && is_numeric($pid)) {
        file_put_contents(PID_FILE, $pid);
        
        // プロセス開始を少し待つ
        sleep(3);
        
        // エラーログを確認
        if (file_exists($errorLogFile)) {
            $errorContent = file_get_contents($errorLogFile);
            if (!empty($errorContent)) {
                echo "[ERR] npm start エラー:\n" . $errorContent . "\n";
            }
        }
        
        // プロセスが実際に開始されたか確認
        if (posix_kill((int)$pid, 0)) {
            echo "[OK] プロセス開始確認 (PID: $pid)\n";
            return true;
        } else {
            echo "[WARN] PID $pid のプロセスが見つかりません\n";
            
            // ログファイルの内容を確認
            if (file_exists(LOG_FILE)) {
                echo "[INFO] 起動ログ (最後の10行):\n";
                $logContent = shell_exec('tail -10 ' . LOG_FILE . ' 2>/dev/null');
                echo $logContent . "\n";
            }
            
            // 代替確認方法
            $nodeCheck = shell_exec('ps aux | grep "npm.*start\|node.*next" | grep -v grep | head -1');
            if (!empty($nodeCheck)) {
                echo "[INFO] Next.jsプロセス検出: " . trim($nodeCheck) . "\n";
                return true;
            }
        }
    }
    
    echo "[ERR] プロセス開始に失敗しました\n";
    return false;
}

/**
 * コマンドをリアルタイム実行してログを出力
 */
function executeWithLiveOutput(string $command, string $workingDir = null): int {
    // proc_open に cwd を明示的に渡す（Apache mod_php では chdir() だけでは
    // サブプロセスの作業ディレクトリが変わらないケースがあるため）
    $resolvedCwd = null;
    if ($workingDir) {
        $resolvedCwd = realpath($workingDir) ?: $workingDir;
        chdir($resolvedCwd);
    } else {
        $resolvedCwd = getcwd() ?: null;
    }
    
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    $process = proc_open($command, $descriptorspec, $pipes, $resolvedCwd);
    
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
            echo "[WARN]Next.jsアプリが見つかりません。先に「[手順１]ウェブサーバーのデータを読み込み<」を実行してください。\n";
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
            $code = executeWithLiveOutput(
                'cd ' . escapeshellarg(NEXT_DIR) . ' && HOME=/root npm install --prefix ' . escapeshellarg(NEXT_DIR) . ' --cache /tmp/.npm 2>&1',
                NEXT_DIR
            );
            echo ($code === 0)
                ? "\n[OK] 依存関係インストール完了\n"
                : "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
            
            if ($code === 0) {
                echo "\n=== 環境変数設定 ===\n";
                flush();
                createNextJsEnvFile();
                
                echo "\n=== ビルド開始 ===\n";
                flush();
                $code = executeWithLiveOutput('cd ' . escapeshellarg(NEXT_DIR) . ' && HOME=/root npm run build 2>&1', NEXT_DIR);
                echo ($code === 0)
                    ? "\n[OK] ビルド完了\n"
                    : "\n[ERR] ビルド失敗 (exit $code)\n";
            }
            
            if ($code === 0) {
                echo "\n=== サーバー起動（ポート3000） ===\n";
                flush();
                
                // 専用関数でNext.jsアプリを起動
                $startResult = startNextJsApp();
                
                if ($startResult) {
                    echo "[完了] Next.jsアプリケーションが正常に起動しました\n";
                } else {
                    echo "[警告] Next.jsアプリケーションの起動に問題があります\n";
                    
                    // 追加のデバッグ情報
                    echo "[INFO] デバッグ情報:\n";
                    $nodeProcesses = shell_exec('ps aux | grep "npm.*start\|node.*next" | grep -v grep');
                    if (!empty($nodeProcesses)) {
                        echo "- Next.jsプロセス: 検出済み\n";
                        echo $nodeProcesses . "\n";
                    } else {
                        echo "- Next.jsプロセス: 未検出\n";
                    }
                    
                    $portCheck = shell_exec('netstat -tlnp | grep :3000 2>/dev/null');
                    if (!empty($portCheck)) {
                        echo "- ポート3000: 使用中\n" . $portCheck . "\n";
                    } else {
                        echo "- ポート3000: 未使用\n";
                    }
                }
            }
        } else {
            // 環境変数ファイル作成
            echo "=== 環境変数設定 ===\n";
            createNextJsEnvFile();
            
            echo "=== 依存関係インストール ===\n";
            chdir(NEXT_DIR);
            passthru('cd ' . escapeshellarg(NEXT_DIR) . ' && npm install --prefix ' . escapeshellarg(NEXT_DIR) . ' 2>&1', $code);
            echo ($code === 0)
                ? "[OK] 依存関係インストール完了\n"
                : "[ERR] 依存関係インストール失敗 (exit $code)\n";

            if ($code === 0) {
                echo "=== Next.jsビルド実行 ===\n";
                passthru('HOME=/root npm run build 2>&1', $code);
                echo ($code === 0)
                    ? "[OK] ビルド完了\n"
                    : "[ERR] ビルド失敗 (exit $code)\n";
            }

            if ($code === 0) {
                // 専用関数でNext.jsアプリを起動
                $startResult = startNextJsApp();
                
                if (!$startResult) {
                    echo "[ERR] Next.jsアプリケーションの起動に失敗しました\n";
                    $code = 1; // エラーコードを設定
                }
            }
            
            echo ($code === 0)
                ? "\n[完了] 全工程が正常に完了しました\n"
                : "\n[失敗] エラーが発生しました (exit $code)\n";
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
            $code = executeWithLiveOutput(
                'cd ' . escapeshellarg(NEXT_DIR) . ' && HOME=/root npm install --prefix ' . escapeshellarg(NEXT_DIR) . ' --cache /tmp/.npm 2>&1',
                NEXT_DIR
            );
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
        echo "=== Webサーバー停止 ===\n";
        echo "[INFO]リバースプロキシ(nginx)のみを停止します\n";
        echo "[INFO]Next.jsアプリケーションは稼働を継続します\n\n";
        
        // nginx停止
        echo "--- リバースプロキシ停止 ---\n";
        stopNginx();
        
        echo "\n[OK]Webサーバーの停止が完了しました\n";
        echo "[INFO]Next.jsアプリは http://localhost:3000 で直接アクセス可能です\n";
        echo "[INFO]再開するには「🔄 Webサーバー再起動」ボタンを使用してください\n";
        break;

    case 'restart':
        echo "=== Webサーバー再起動 ===\n";
        echo "[INFO]リバースプロキシ(nginx)のみを再起動します\n";
        echo "[INFO]Next.jsアプリケーションは稼働を継続します\n\n";
        
        // nginx再起動
        echo "--- リバースプロキシ再起動 ---\n";
        restartNginx();
        
        echo "\n[OK]Webサーバーの再起動が完了しました\n";
        echo "[INFO]リバースプロキシは http://localhost (ポート80) で稼働中です\n";
        break;

    case 'status':
        echo "=== Next.js アプリ状態確認 ===\n";
        
        // PIDファイル確認
        if (isRunning()) {
            $pid = (int)trim(file_get_contents(PID_FILE));
            echo "[PIDファイル] RUNNING - PID: $pid\n";
        } else {
            echo "[PIDファイル] STOPPED\n";
        }
        
        // プロセス確認
        echo "\n-- プロセス検索 --\n";
        $nodeProcesses = shell_exec('ps aux | grep "npm.*start\|node.*next" | grep -v grep');
        if (!empty($nodeProcesses)) {
            echo "[プロセス] Next.js関連プロセス発見:\n";
            echo $nodeProcesses . "\n";
        } else {
            echo "[プロセス] Next.js関連プロセスなし\n";
        }
        
        // ポート使用状況確認
        echo "\n-- ポート使用状況 --\n";
        $portCheck = shell_exec('netstat -tlnp | grep :3000 2>/dev/null');
        if (!empty($portCheck)) {
            echo "[ポート3000] 使用中:\n" . $portCheck . "\n";
        } else {
            echo "[ポート3000] 使用されていません\n";
        }
        
        // アクセステスト
        echo "\n-- アクセステスト --\n";
        $curlTest = shell_exec('curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 2>/dev/null');
        if ($curlTest == '200') {
            echo "[アクセス] OK - Next.jsアプリは正常に動作中\n";
        } else {
            echo "[アクセス] NG - HTTPレスポンス: $curlTest\n";
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

    case 'env':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR)) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        // 環境変数ファイル作成
        echo "=== Next.js 環境変数設定 ===\n";
        if (createNextJsEnvFile()) {
            echo "[OK] 環境変数ファイルを作成しました\n";
            
            // 環境変数ファイルの内容を表示
            $envPath = NEXT_DIR . "/.env.local";
            if (file_exists($envPath)) {
                echo "\n-- .env.local の内容 --\n";
                echo file_get_contents($envPath);
                echo "\n-- ファイルパス: $envPath --\n";
            }
        } else {
            echo "[ERROR] 環境変数ファイルの作成に失敗しました\n";
        }
        break;

    case 'install':
        // Next.jsディレクトリの存在確認
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        if ($isStreaming) {
            echo "=== npm環境準備 ===\n";
            flush();
            
            // ローカルキャッシュディレクトリ作成
            $localCache = NEXT_DIR . '/.npm-cache';
            $localTmp = NEXT_DIR . '/.tmp';
            
            echo "[INFO]ローカルキャッシュディレクトリを準備中: $localCache\n";
            passthru("mkdir -p $localCache $localTmp");
            passthru("chmod -R 755 $localCache $localTmp");
            
            // EEXIST エラー対策: 既存キャッシュをクリア
            echo "[INFO]既存キャッシュをクリア中...\n";
            passthru("rm -rf $localCache/* $localTmp/* 2>/dev/null || true");
            
            echo "=== 依存関係インストール開始 ===\n";
            flush();
            
            // 環境変数を設定してnpm install実行
            $envVars = [
                'HOME=/root',
                "TMPDIR=$localTmp",
                "npm_config_cache=$localCache",
                'npm_config_progress=false',
                'npm_config_loglevel=info'
            ];
            $envString = implode(' ', $envVars);
            $npmCommand = "$envString npm install --prefer-offline --no-audit --no-fund 2>&1";
            
            echo "[INFO]実行コマンド: npm install --prefer-offline --no-audit --no-fund\n";
            echo "[INFO]キャッシュディレクトリ: $localCache\n";
            echo "[INFO]一時ディレクトリ: $localTmp\n\n";
            
            $code = executeWithLiveOutput($npmCommand, NEXT_DIR);
            
            if ($code === 0) {
                echo "\n[OK] 依存関係インストール完了\n";
                echo "[INFO]キャッシュサイズ: ";
                passthru("du -sh $localCache 2>/dev/null || echo '不明'");
            } else {
                echo "\n[ERR] 依存関係インストール失敗 (exit $code)\n";
                echo "\n=== トラブルシューティング ===\n";
                echo "1. 手動でキャッシュクリア: rm -rf " . NEXT_DIR . "/.npm-cache/*\n";
                echo "2. 手動でインストール試行: cd " . NEXT_DIR . " && npm install\n";
                echo "3. npm バージョン確認: npm --version\n";
                echo "4. Node.js バージョン確認: node --version\n";
                
                // エラーログの詳細表示
                $errorLog = '/tmp/npm-debug*.log';
                echo "\n[INFO]npmエラーログ確認:\n";
                passthru("ls -la $errorLog 2>/dev/null && tail -20 $errorLog 2>/dev/null || echo 'エラーログが見つかりません'");
            }
        }
        break;

    case 'manual-install':
        echo "=== 手動npm install実行 ===\n";
        
        if (!is_dir(NEXT_DIR) || !file_exists(NEXT_DIR . '/package.json')) {
            echo "[WARN]Next.jsアプリが見つかりません。先に「GitHubから最新版を取得」を実行してください。\n";
            break;
        }
        
        echo "[INFO]手動インストールコマンド:\n\n";
        echo "cd " . NEXT_DIR . "\n";
        echo "export TMPDIR=\"\$(pwd)/.tmp\"\n";
        echo "export npm_config_cache=\"\$(pwd)/.npm-cache\"\n";
        echo "mkdir -p .tmp .npm-cache\n";
        echo "rm -rf .npm-cache/* .tmp/* node_modules 2>/dev/null || true\n";
        echo "npm install --prefer-offline --no-audit --no-fund\n\n";
        
        echo "[INFO]上記コマンドをターミナルで実行してください。\n";
        break;    

    case 'deploy':
        // デプロイ処理（Git更新 → 環境変数設定 → ビルド → 起動）
        if ($isStreaming) {
            echo "=== デプロイ開始 ===\n\n";

            // 1. Next.jsプロジェクト存在確認（未配置なら取得を試行）
            echo "--- STEP 1: Next.jsプロジェクト確認 ---\n";
            flush();
            if (!ensureNextJsProjectAvailable(true)) {
                echo "[ERR] Next.jsプロジェクトが準備できません\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            echo "[OK] Next.jsプロジェクトを確認しました\n\n";
            
            // 2. .envから環境変数を.env.localにコピー
            echo "--- STEP 2: 環境変数を設定 ---\n";
            flush();
            $envSource = BASE_DIR . '/.env';
            $envDest = NEXT_DIR . '/.env.local';
            
            if (file_exists($envSource)) {
                if (copy($envSource, $envDest)) {
                    echo "[OK] 環境変数ファイルを設定しました\n";
                    echo "[INFO] " . $envSource . " → " . $envDest . "\n\n";
                } else {
                    echo "[WARN] 環境変数ファイルのコピーに失敗しました\n\n";
                }
            } else {
                echo "[WARN] .envファイルが見つかりません\n";
                echo "[INFO] デフォルト設定で続行します\n\n";
            }
            
            // 3. 完全クリーンアップ（node_modules含む）
            echo "--- STEP 3: 完全クリーンアップ ---\n";
            flush();
            
            if (!ensureNextJsProjectAvailable(false)) {
                echo "[ERR] Next.jsプロジェクトが見つかりません\n";
                break;
            }
            
            // .nextディレクトリの事前削除（パーミッション問題の根本対策）
            $nextPath = NEXT_DIR . '/.next';
            if (is_dir($nextPath)) {
                echo "[INFO] .nextディレクトリを事前削除中...\n";
                passthru("chown -R root:root " . escapeshellarg($nextPath) . " 2>/dev/null || true");
                passthru("chmod -R 777 " . escapeshellarg($nextPath) . " 2>/dev/null || true");
                passthru("rm -rf " . escapeshellarg($nextPath) . " 2>&1 || true");
                
                // findコマンドで個別削除を試行
                if (is_dir($nextPath)) {
                    echo "[INFO] 通常削除失敗。個別削除を試行中...\n";
                    passthru("find " . escapeshellarg($nextPath) . " -type f -delete 2>&1 || true");
                    passthru("find " . escapeshellarg($nextPath) . " -type d -delete 2>&1 || true");
                    passthru("rm -rf " . escapeshellarg($nextPath) . " 2>&1 || true");
                }
                
                if (!is_dir($nextPath)) {
                    echo "[OK] .nextディレクトリを削除しました\n\n";
                } else {
                    echo "[WARN] .nextディレクトリが削除できません。次の手段に進みます...\n\n";
                }
            }
            
            chdir(NEXT_DIR);
            
            echo "[WARN] Next.jsアプリを完全にクリーンアップします\n";
            echo "[INFO] node_modules、.next、キャッシュを全て削除します...\n\n";
            
            // 削除対象のディレクトリ/ファイルリスト
            $toDelete = [
                '.next' => '.nextディレクトリ（ビルド成果物）',
                'node_modules' => 'node_modulesディレクトリ（依存関係）',
                '.npm-cache' => 'npmキャッシュ',
                '.tmp' => '一時ファイル',
                'package-lock.json' => 'package-lock.json（ロックファイル）'
            ];
            
            foreach ($toDelete as $path => $description) {
                if (file_exists($path)) {
                    echo "[INFO] $description を削除中...\n";
                    
                    // 削除前にパーミッションを変更（書き込み可能にする）
                    if (is_dir($path)) {
                        echo "[INFO] パーミッションを変更中...\n";
                        // chownでrootに変更してから削除（特にnode_modules内のバイナリファイル対策）
                        passthru("chown -R root:root " . escapeshellarg($path) . " 2>/dev/null || true");
                        passthru("chmod -R 777 " . escapeshellarg($path) . " 2>/dev/null || true");
                        
                        // 通常削除を試行
                        passthru("rm -rf " . escapeshellarg($path) . " 2>&1", $code);
                        
                        // 失敗した場合は強制削除（findコマンドで個別に削除）
                        if ($code !== 0 && file_exists($path)) {
                            echo "[INFO] 通常削除失敗、強制削除を試行中...\n";
                            passthru("find " . escapeshellarg($path) . " -delete 2>&1 || true");
                            
                            // それでも残っている場合は警告のみ
                            if (file_exists($path)) {
                                echo "[WARN] 一部ファイルが削除できませんでした。npm installで上書きされます\n";
                            }
                        }
                    } else {
                        passthru("chown root:root " . escapeshellarg($path) . " 2>/dev/null || true");
                        passthru("chmod 666 " . escapeshellarg($path) . " 2>/dev/null || true");
                        passthru("rm -f " . escapeshellarg($path) . " 2>&1", $code);
                    }
                    
                    if ($code === 0 && !file_exists($path)) {
                        echo "[OK] $description を削除しました\n";
                    } else {
                        echo "[WARN] $description の削除に失敗しました（続行します）\n";
                    }
                } else {
                    echo "[INFO] $description は存在しません（スキップ）\n";
                }
            }
            
            // npmグローバルキャッシュもクリア
            echo "\n[INFO] npmグローバルキャッシュをクリーン...\n";
            passthru('npm cache clean --force 2>&1', $cacheCode);
            if ($cacheCode === 0) {
                echo "[OK] npmキャッシュをクリアしました\n";
            }
            
            // 削除確認
            echo "\n[INFO] クリーンアップ結果を確認中...\n";
            $remainingIssues = [];
            if (is_dir('.next')) $remainingIssues[] = '.next';
            if (is_dir('node_modules')) $remainingIssues[] = 'node_modules';
            
            if (empty($remainingIssues)) {
                echo "[OK] すべてのキャッシュとビルド成果物を削除しました\n";
            } else {
                echo "[WARN] 一部のファイルが残っています: " . implode(', ', $remainingIssues) . "\n";
                echo "[INFO] 続行しますが、問題が発生する可能性があります\n";
            }
            
            echo "\n";
            
            // 4. 依存関係の再インストール
            echo "--- STEP 4: 依存関係の再インストール ---\n";
            flush();
            
            echo "[INFO] package.jsonから依存関係を再インストール中...\n";
            echo "[INFO] これには数分かかる場合があります...\n\n";

            // package.json の存在を確認（診断情報付き）
            $pkgJsonPath = NEXT_DIR . '/package.json';
            echo "[INFO] 作業ディレクトリ確認: " . NEXT_DIR . "\n";
            echo "[INFO] package.json パス確認: " . $pkgJsonPath . "\n";
            echo "[INFO] is_file チェック: " . (is_file($pkgJsonPath) ? 'OK（存在）' : 'NG（存在しない）') . "\n";
            passthru('ls -la ' . escapeshellarg(NEXT_DIR) . ' 2>&1 | head -20');

            if (!is_file($pkgJsonPath)) {
                echo "[ERR] package.json が見つかりません: " . $pkgJsonPath . "\n";
                echo "[INFO] GITURL: " . (getenv('GITURL') ?: '（未設定）') . "\n";
                echo "[INFO] 先に『GitHubから最新版を取得』を実行するか、GITURL を確認してください\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            
            // npm install — cd と --prefix で作業ディレクトリを二重に固定する
            // (proc_open に cwd を渡すようにしたが、念のためシェル側でも明示)
            $nextDirEsc = escapeshellarg(NEXT_DIR);
            $installCmd = 'cd ' . $nextDirEsc . ' && ' .
                         'export TMPDIR=' . $nextDirEsc . '/.tmp && ' .
                         'export npm_config_cache=' . $nextDirEsc . '/.npm-cache && ' .
                         'mkdir -p ' . $nextDirEsc . '/.tmp ' . $nextDirEsc . '/.npm-cache && ' .
                         'npm install --prefix ' . $nextDirEsc . ' --prefer-offline --no-audit --no-fund 2>&1';
            
            $installCode = executeWithLiveOutput($installCmd, NEXT_DIR);
            
            if ($installCode !== 0) {
                echo "\n[ERR] 依存関係のインストールに失敗しました (exit $installCode)\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            
            echo "\n[OK] 依存関係のインストールが完了しました\n";
            
            // node_modules確認
            if (is_dir('node_modules') && is_dir('node_modules/.bin')) {
                echo "[OK] node_modulesが正常に生成されました\n";
            } else {
                echo "[ERR] node_modulesの生成に失敗しました\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            
            echo "\n";
            
            // 5. ビルド実行
            echo "--- STEP 5: ビルド実行 ---\n";
            flush();
            
            // ビルド直前に.nextを再度削除（パーミッション問題対策）
            if (file_exists('.next')) {
                echo "[INFO] ビルド前に.nextディレクトリを再削除中...\n";
                passthru("chown -R root:root .next 2>&1 || true");
                passthru("chmod -R 777 .next 2>&1 || true");
                passthru("rm -rf .next 2>&1 || true");
                
                if (file_exists('.next')) {
                    echo "[WARN] .nextの削除に失敗しましたが、ビルドを続行します\n";
                } else {
                    echo "[OK] .nextを削除しました\n";
                }
            }
            
            $buildCode = executeWithLiveOutput('npm run build 2>&1', NEXT_DIR);
            
            if ($buildCode !== 0) {
                echo "\n[ERR] ビルドに失敗しました (exit $buildCode)\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            
            echo "\n[OK] ビルドが完了しました\n";
            
            // ビルド結果の確認
            echo "[INFO] ビルド成果物を確認中...\n";
            if (is_dir('.next')) {
                // ビルドIDの確認
                if (file_exists('.next/BUILD_ID')) {
                    $buildId = trim(file_get_contents('.next/BUILD_ID'));
                    echo "[OK] ビルドID: $buildId\n";
                }
                
                // 主要ディレクトリの確認
                $requiredDirs = ['.next/static', '.next/server', '.next/cache'];
                $allExists = true;
                foreach ($requiredDirs as $dir) {
                    if (!is_dir($dir)) {
                        echo "[WARN] $dir が見つかりません\n";
                        $allExists = false;
                    }
                }
                
                if ($allExists) {
                    echo "[OK] すべてのビルド成果物が正常に生成されました\n";
                } else {
                    echo "[WARN] 一部のビルド成果物が不足していますが続行します\n";
                }
            } else {
                echo "[ERR] .nextディレクトリが生成されませんでした\n";
                echo "[INFO] デプロイを中断します\n";
                break;
            }
            
            echo "\n";
            
            // 6. Next.jsアプリケーション完全停止
            echo "--- STEP 6: 既存アプリケーション完全停止 ---\n";
            flush();
            
            // 既存プロセスを停止
            if (isRunning()) {
                $pid = (int)trim(file_get_contents(PID_FILE));
                echo "[INFO] 既存プロセスにSIGTERMを送信中 (PID: $pid)...\n";
                posix_kill($pid, SIGTERM);
                sleep(2);
                
                // まだ生きている場合はSIGKILL
                if (posix_kill($pid, 0)) {
                    echo "[WARN] プロセスが終了しないためSIGKILLを送信...\n";
                    posix_kill($pid, SIGKILL);
                    sleep(1);
                }
                
                if (file_exists(PID_FILE)) {
                    unlink(PID_FILE);
                }
                echo "[OK] 既存プロセスを停止しました (PID: $pid)\n";
            } else {
                echo "[INFO] 実行中のプロセスはありません\n";
            }
            
            // ポート3000の完全クリーンアップ
            echo "[INFO] ポート3000の全プロセスを停止中...\n";
            killPort3000Processes();
            sleep(2);
            
            // 停止確認
            exec("lsof -ti:3000 2>/dev/null", $portCheck, $portCode);
            if ($portCode === 0 && !empty($portCheck)) {
                echo "[WARN] まだポート3000が使用中です。強制終了します...\n";
                exec("kill -9 " . implode(' ', $portCheck) . " 2>/dev/null");
                sleep(1);
            }
            echo "[OK] ポート3000のクリーンアップ完了\n";
            
            // ログファイルをクリア（新しいログのため）
            if (file_exists(LOG_FILE)) {
                echo "[INFO] 古いログファイルをクリア中...\n";
                file_put_contents(LOG_FILE, '');
                echo "[OK] ログファイルをクリアしました\n";
            }
            
            echo "\n";
            
            // 7. Next.jsアプリケーション起動
            echo "--- STEP 7: アプリケーション起動 ---\n";
            flush();
            
            // 新しいプロセスを起動
            chdir(NEXT_DIR);
            $cmd = sprintf(
                'nohup npm run start > %s 2>&1 & echo $!',
                escapeshellarg(LOG_FILE)
            );
            $pid = shell_exec($cmd);
            file_put_contents(PID_FILE, trim($pid));
            echo "[OK] Next.jsアプリを起動しました (PID: " . trim($pid) . ")\n";
            
            // 起動確認（最大20秒待機）
            echo "[INFO] アプリケーションの起動を確認中...\n";
            $maxWait = 20;
            $waited = 0;
            $started = false;
            $ready = false;
            
            while ($waited < $maxWait) {
                sleep(1);
                $waited++;
                
                // ポート3000がリッスンしているか確認
                exec("lsof -ti:3000 2>/dev/null", $output, $code);
                if ($code === 0 && !empty($output)) {
                    if (!$started) {
                        $started = true;
                        echo "[OK] ポート3000がリッスン開始 ({$waited}秒)\n";
                    }
                    
                    // HTTPレスポンスを確認
                    $httpCode = @file_get_contents('http://localhost:3000', false, stream_context_create([
                        'http' => [
                            'timeout' => 2,
                            'ignore_errors' => true
                        ]
                    ]));
                    
                    if ($httpCode !== false) {
                        $ready = true;
                        echo "[OK] アプリケーションが完全に起動しました ({$waited}秒)\n";
                        break;
                    }
                }
                
                if ($waited % 3 === 0) {
                    echo "[INFO] 起動待機中... ({$waited}/{$maxWait}秒)\n";
                    flush();
                }
            }
            
            if (!$started) {
                echo "[ERR] アプリケーションの起動に失敗しました\n";
                echo "[INFO] ログを確認してください: " . LOG_FILE . "\n";
            } elseif (!$ready) {
                echo "[WARN] HTTPレスポンスの確認がタイムアウトしましたが続行します\n";
            }
            
            // 追加の安定化待機
            echo "[INFO] 安定化を待機中...\n";
            sleep(3);
            
            echo "\n";
            
            // 8. nginx再起動
            echo "--- STEP 8: リバースプロキシ再起動 ---\n";
            flush();
            restartNginx();
            
            echo "\n=== デプロイ完了 ===\n";
            echo "[OK] すべての処理が正常に完了しました\n";
            echo "[INFO] アプリケーションは http://localhost で公開されています\n";
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

    case 'debug':
        // Next.js詳細デバッグ
        echo "=== Next.js 詳細デバッグ ===\n";
        
        // 作業ディレクトリ確認
        echo "[INFO] 現在の作業ディレクトリ: " . getcwd() . "\n";
        echo "[INFO] Next.jsディレクトリ: " . NEXT_DIR . "\n";
        
        if (is_dir(NEXT_DIR)) {
            chdir(NEXT_DIR);
            echo "[OK] Next.jsディレクトリに移動しました\n";
            echo "[INFO] 移動後の作業ディレクトリ: " . getcwd() . "\n";
            
            // package.json確認
            if (file_exists('package.json')) {
                $packageJson = json_decode(file_get_contents('package.json'), true);
                echo "[OK] package.json読み込み成功\n";
                if (isset($packageJson['scripts']['start'])) {
                    echo "[INFO] start script: " . $packageJson['scripts']['start'] . "\n";
                } else {
                    echo "[WARN] startスクリプトが見つかりません\n";
                }
                if (isset($packageJson['scripts']['build'])) {
                    echo "[INFO] build script: " . $packageJson['scripts']['build'] . "\n";
                }
            } else {
                echo "[ERR] package.jsonが見つかりません\n";
            }
            
            // .nextディレクトリ確認
            if (is_dir('.next')) {
                echo "[OK] .nextディレクトリが存在します\n";
                $nextFiles = shell_exec('ls -la .next/ 2>/dev/null | head -10');
                echo $nextFiles . "\n";
            } else {
                echo "[ERR] .nextディレクトリが見つかりません（ビルドが必要）\n";
            }
            
            // node_modulesディレクトリ確認
            if (is_dir('node_modules')) {
                echo "[OK] node_modulesディレクトリが存在します\n";
                if (file_exists('node_modules/.bin/next')) {
                    echo "[OK] nextコマンドが利用可能です\n";
                } else {
                    echo "[WARN] nextコマンドが見つかりません\n";
                }
            } else {
                echo "[ERR] node_modulesディレクトリが見つかりません（npm installが必要）\n";
            }
            
            // npm/nodeコマンド確認
            echo "\n-- コマンド確認 --\n";
            $npmPath = shell_exec('which npm 2>/dev/null');
            if (!empty($npmPath)) {
                echo "[OK] npm: " . trim($npmPath) . "\n";
                $npmVersion = shell_exec('npm --version 2>/dev/null');
                echo "[INFO] npm version: " . trim($npmVersion) . "\n";
            } else {
                echo "[ERR] npmが見つかりません\n";
            }
            
            $nodePath = shell_exec('which node 2>/dev/null');
            if (!empty($nodePath)) {
                echo "[OK] node: " . trim($nodePath) . "\n";
                $nodeVersion = shell_exec('node --version 2>/dev/null');
                echo "[INFO] node version: " . trim($nodeVersion) . "\n";
            } else {
                echo "[ERR] nodeが見つかりません\n";
            }
            
            // 手動でnpm run startを試行
            echo "\n-- 手動npm run start試行 --\n";
            echo "[INFO] 次のコマンドを手動実行します: npm run start\n";
            $startOutput = shell_exec('timeout 10 npm run start 2>&1 | head -20');
            echo "出力:\n" . $startOutput . "\n";
            
        } else {
            echo "[ERR] Next.jsディレクトリが見つかりません\n";
        }
        break;

    case 'manual-start':
        // Next.js手動起動
        echo "=== Next.js手動起動 ===\n";
        
        if (!is_dir(NEXT_DIR)) {
            echo "[ERR] Next.jsディレクトリが見つかりません\n";
            break;
        }
        
        chdir(NEXT_DIR);
        
        // 既存プロセス停止
        killPort3000Processes();
        
        echo "[INFO] 作業ディレクトリ: " . getcwd() . "\n";
        echo "[INFO] シンプルなnpm run start実行を試行...\n";
        
        // シンプルなバックグラウンド実行
        $command = 'nohup npm run start > /var/www/html/logs/nextjs.log 2>&1 &';
        echo "[INFO] 実行コマンド: $command\n";
        
        shell_exec($command);
        
        echo "[INFO] コマンド実行完了。5秒待機後に状況確認...\n";
        sleep(5);
        
        // プロセス確認
        $processes = shell_exec('ps aux | grep "npm.*start\|node.*next" | grep -v grep');
        if (!empty($processes)) {
            echo "[OK] Next.jsプロセス検出:\n" . $processes . "\n";
        } else {
            echo "[WARN] Next.jsプロセスが見つかりません\n";
        }
        
        // ポート確認
        $portCheck = shell_exec('netstat -tlnp | grep :3000 2>/dev/null');
        if (!empty($portCheck)) {
            echo "[OK] ポート3000使用中:\n" . $portCheck . "\n";
        } else {
            echo "[WARN] ポート3000は使用されていません\n";
        }
        
        // ログ確認
        if (file_exists('/var/www/html/logs/nextjs.log')) {
            echo "[INFO] 最新のログ (最後の10行):\n";
            $logContent = shell_exec('tail -10 /var/www/html/logs/nextjs.log');
            echo $logContent . "\n";
        }
        break;

    case 'port80check':
        // ポート80の使用状況確認
        echo "=== ポート80使用状況確認 ===\n";
        
        // Dockerコンテナ内のポート80確認
        echo "[INFO] コンテナ内のポート80確認:\n";
        $containerPort80 = shell_exec('ss -tlnp | grep :80 2>/dev/null || netstat -tlnp | grep :80 2>/dev/null || echo "コンテナ内でポート80は使用されていません"');
        echo $containerPort80 . "\n";
        
        // nginxプロセス確認
        echo "[INFO] nginxプロセス確認:\n";
        $nginxProcesses = shell_exec('ps aux | grep nginx | grep -v grep');
        if (!empty($nginxProcesses)) {
            echo $nginxProcesses . "\n";
        } else {
            echo "nginxプロセスが見つかりません\n";
        }
        
        // supervisor状態確認
        echo "[INFO] supervisor状態確認:\n";
        if (file_exists('/usr/bin/supervisorctl')) {
            passthru('supervisorctl status');
        } else {
            echo "supervisorctlが見つかりません\n";
        }
        break;

    default:
        echo "不正なアクションです\n";
        break;
}
