<?php

// 環境変数から認証情報を読み込み
function loadAuthConfig() {
    $config = [];
    
    // 1. .env.authファイルから読み込み
    $envAuthFile = __DIR__ . '/../.env.auth';
    if (file_exists($envAuthFile)) {
        $lines = file($envAuthFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // コメント行をスキップ
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value, '"\'');
            }
        }
    }
    
    // 2. 環境変数から読み込み（優先）
    if (getenv('AUTH_USERNAME')) $config['AUTH_USERNAME'] = getenv('AUTH_USERNAME');
    if (getenv('AUTH_PASSWORD')) $config['AUTH_PASSWORD'] = getenv('AUTH_PASSWORD');
    if (getenv('AUTH_REALM')) $config['AUTH_REALM'] = getenv('AUTH_REALM');
    
    // 3. デフォルト値（フォールバック）
    if (!isset($config['AUTH_USERNAME'])) $config['AUTH_USERNAME'] = 'admin';
    if (!isset($config['AUTH_PASSWORD'])) $config['AUTH_PASSWORD'] = 'changeme';
    if (!isset($config['AUTH_REALM'])) $config['AUTH_REALM'] = 'Next.js 管理ツール';
    
    return $config;
}

// 認証設定を読み込み
$authConfig = loadAuthConfig();
$realm = $authConfig['AUTH_REALM'];
$users = [
    $authConfig['AUTH_USERNAME'] => $authConfig['AUTH_PASSWORD']
];

// Digest 認証ヘッダーの送出
if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
    exit('認証が必要です');
}

// Digest ヘッダーのパース
if (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) || !isset($users[$data['username']])) {
    // 認証失敗時にブラウザに再認証を促す
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
    exit('ユーザー名またはパスワードが正しくありません。もう一度お試しください。');
}

// 応答値の検証
$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
$A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
$valid_response = md5(
    $A1 . ':' .
    $data['nonce'] . ':' .
    $data['nc']    . ':' .
    $data['cnonce']. ':' .
    $data['qop']   . ':' .
    $A2
);
if ($data['response'] !== $valid_response) {
    // 認証失敗時にブラウザに再認証を促す
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
    exit('パスワードが正しくありません。もう一度お試しください。');
}

// 認証成功後は何もしない（そのまま後続処理へ）

/**
 * PHP の Digest ヘッダー文字列を連想配列に変換する関数
 */
function http_digest_parse(string $txt): array|false {
    $needed_parts = [
        'nonce'    => 1,
        'nc'       => 1,
        'cnonce'   => 1,
        'qop'      => 1,
        'username' => 1,
        'uri'      => 1,
        'response' => 1,
    ];
    $data = [];
    $keys = implode('|', array_keys($needed_parts));

    preg_match_all(
        '@(' . $keys . ')=(?:' .
        '([\'"])([^\2]+?)\2|' .  // quoted values
        '([^\s,]+)' .           // unquoted
        ')@',
        $txt, $matches, PREG_SET_ORDER
    );
    foreach ($matches as $m) {
        $data[$m[1]] = $m[3] ?: $m[4];
        unset($needed_parts[$m[1]]);
    }
    return $needed_parts ? false : $data;
}
