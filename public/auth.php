<?php

// 認証用設定
$realm = 'Next.js 管理ツール';
$users = [
    // ユーザー名 => パスワード
    'admin' => 'aimgstaff',
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
