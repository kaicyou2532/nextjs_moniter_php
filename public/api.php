<?php
// Web ルート外のディレクトリへのパス
require __DIR__ . '/auth.php';
define('BASE_DIR', realpath(__DIR__ . '/../'));
define('NEXT_DIR', BASE_DIR . '/next-app');
define('PID_FILE', BASE_DIR . '/pids/nextjs.pid');
define('LOG_FILE', BASE_DIR . '/logs/nextjs.log');

header('Content-Type: text/plain; charset=UTF-8');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

function isRunning() {
    if (!file_exists(PID_FILE)) return false;
    $pid = trim(file_get_contents(PID_FILE));
    return $pid && posix_kill((int)$pid, 0);
}

switch ($action) {
  case 'build':
    chdir(NEXT_DIR);
    // npm install が済んでいる前提
    passthru('npm run build 2>&1', $code);
    echo ($code === 0 ? "[OK] ビルド完了\n" : "[ERR] ビルド失敗 (exit $code)\n");
    break;

  case 'start':
    if (isRunning()) {
      echo "[WARN] すでに起動中\n";
      break;
    }
    chdir(NEXT_DIR);
    // nohup でバックグラウンド起動し、PID を保存
    $cmd = sprintf(
      'nohup npm run start > %s 2>&1 & echo $!',
      escapeshellarg(LOG_FILE)
    );
    $pid = shell_exec($cmd);
    file_put_contents(PID_FILE, $pid);
    echo "[OK] 起動しました (PID: $pid)\n";
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
    file_put_contents(PID_FILE, $pid);
    echo "[OK] WEBアプリを再起動しました (PID: $pid)\n";
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

  default:
    echo "不正なアクションです\n";
    break;
}

/**
 * ファイル末尾を取得
 */
function tail(string $file, int $lines = 10): string {
  $fp = fopen($file, 'r');
  $pos = -2;
  $data = '';
  $lineCount = 0;
  fseek($fp, $pos, SEEK_END);
  while ($lineCount < $lines) {
    $char = fgetc($fp);
    if ($char === "\n") $lineCount++;
    $data = $char . $data;
    fseek($fp, --$pos, SEEK_END);
    if (ftell($fp) <= 0) {
      rewind($fp);
      break;
    }
  }
  fclose($fp);
  return $data;
}
