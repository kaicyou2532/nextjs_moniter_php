# Next.js PHP 管理ツール

## 概要 / Overview
PHP ベースの Web インターフェースから Next.js アプリケーションを  
- ビルド  
- 起動  
- 停止  
- 再起動  
- 状態確認  
- Git プル  
- 環境変数管理  
- リアルタイムログ監視  

できる多機能な管理ツールです。Docker 対応と Digest 認証による安全なアクセス制御を提供します。  

This is a comprehensive PHP-based web tool to manage Next.js applications with features like build automation, process management, Git integration, environment variable management, real-time log monitoring, Docker support, and secure Digest authentication.

---

## 目次 / Table of Contents
1. [前提条件 / Prerequisites](#前提条件--prerequisites)  
2. [ディレクトリ構成 / Directory Structure](#ディレクトリ構成--directory-structure)  
3. [インストール / Installation](#インストール--installation)  
4. [設定 / Configuration](#設定--configuration)  
5. [認証設定 / Authentication](#認証設定--authentication)  
6. [環境変数設定 / Environment Variables](#環境変数設定--environment-variables)  
7. [Docker 使用方法 / Docker Usage](#docker-使用方法--docker-usage)  
8. [使い方 / Usage](#使い方--usage)  
9. [パーミッション / Permissions](#パーミッション--permissions)  
10. [macOS での検証 / macOS Testing](#macos-での検証--macos-testing)  
11. [トラブルシューティング / Troubleshooting](#トラブルシューティング--troubleshooting)  
12. [ライセンス / License](#ライセンス--license)

---

## 前提条件 / Prerequisites
- **PHP 8.0 以上**（CLI と `exec` / `shell_exec` が有効）  
- **Node.js 18+ と npm**  
- **Next.js プロジェクト**（`next-app/` 配下）  
- **Web サーバー** (Apache/nginx + PHP-FPM、または組み込みサーバー)  
- **Git** (自動 Git プル機能用)  
- **Docker & Docker Compose** (コンテナ実行用 - オプション)  
- **supervisor** (Docker 内でのマルチプロセス管理用)  

---

## ディレクトリ構成 / Directory Structure

```
nextjs-monitor-php/
├── public/
│   ├── index.php       ← フロントエンド UI (認証付き管理画面)
│   ├── api.php         ← バックエンド API (各種操作エンドポイント)
│   └── auth.php        ← Digest 認証処理
├── next-app/           ← Next.js プロジェクト配置先
├── logs/
│   └── nextjs.log      ← アプリケーションログ出力先
├── pids/
│   └── nextjs.pid      ← プロセス ID 保存先
├── .env.auth           ← 認証情報 (Git 除外対象)
├── .env.auth.example   ← 認証設定テンプレート
├── .gitignore          ← Git 除外ファイル設定
├── docker-compose.yml  ← Docker 構成ファイル
├── Dockerfile          ← Docker イメージ定義
├── nginx.conf          ← nginx リバースプロキシ設定
├── supervisord.conf    ← マルチプロセス管理設定
└── README.md           ← このファイル
```

**重要なポイント:**
- `public/` を Web ドキュメントルートに設定  
- `logs/` と `pids/` は PHP プロセスから書き込み可能にする  
- `.env.auth` ファイルは Git 管理対象外 (認証情報保護)

---

## インストール / Installation

### 1. リポジトリクローン
```bash
git clone <your-repository-url>
cd nextjs_moniter_php
```

### 2. Next.js プロジェクトセットアップ
```bash
cd next-app
npm install
npm run build  # 初回ビルド
cd ..
```

### 3. 必要なディレクトリを作成
```bash
mkdir -p logs pids
chmod 775 logs pids
```

### 4. Web サーバー設定
**Apache の場合:**
```apache
DocumentRoot /path/to/nextjs_moniter_php/public
<Directory "/path/to/nextjs_moniter_php/public">
    AllowOverride All
    Require all granted
</Directory>
```

**nginx + PHP-FPM の場合:**
```nginx
server {
    listen 80;
    root /path/to/nextjs_moniter_php/public;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

---

## 設定 / Configuration

### 基本設定の調整
`public/api.php` 内の以下の定数を環境に合わせて調整:

```php
define('BASE_DIR', '/Users/nakamurakiichi/nextjs_moniter_php');
define('NEXT_DIR', BASE_DIR . '/next-app');
define('LOG_FILE', BASE_DIR . '/logs/nextjs.log');
define('PID_FILE', BASE_DIR . '/pids/nextjs.pid');
```

### Next.js ポート設定
`package.json` で Next.js の起動ポートを確認/設定:
```json
{
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "start": "next start -p 3000"
  }
}
```

### Git リポジトリ設定
自動 Git プル機能を使用する場合、`public/api.php` 内で:
```php
const GIT_REPO_URL = 'https://github.com/AIM-SC/next-website.git';
```

---

## 認証設定 / Authentication

### 1. 認証ファイル作成
```bash
# テンプレートをコピー
cp .env.auth.example .env.auth

# 認証情報を編集
nano .env.auth
```

### 2. 認証情報設定
`.env.auth` ファイルに以下の形式で設定:
```bash
AUTH_USERNAME=admin
AUTH_PASSWORD=your_secure_password
AUTH_REALM=Next.js 管理ツール
```

### 3. セキュリティ確認
- `.env.auth` ファイルが `.gitignore` に含まれていることを確認
- パスワードは十分に複雑なものを使用
- 本番環境では HTTPS を使用することを推奨

---

## 環境変数設定 / Environment Variables

### Next.js 環境変数の管理
管理画面から直接 Next.js の環境変数を設定できます:

1. **環境変数作成ボタン** をクリック
2. 以下の形式で自動生成される `.env.local`:
```bash
MICROCMS_SERVICE_DOMAIN=your-service-domain
MICROCMS_API_KEY=your-api-key
```

3. 必要に応じて `next-app/.env.local` を手動編集

### 環境変数テンプレート
`next-app/.env.local.example` ファイルを作成してテンプレート化:
```bash
MICROCMS_SERVICE_DOMAIN=
MICROCMS_API_KEY=
NEXT_PUBLIC_BASE_URL=
```

---

## Docker 使用方法 / Docker Usage

### 1. Docker Compose でのビルド・起動
```bash
# コンテナビルド・起動
docker-compose up --build

# バックグラウンド起動
docker-compose up -d --build

# 停止
docker-compose down
```

### 2. アクセス方法
- **管理画面**: http://localhost (ポート 80)
- **Next.js アプリ**: http://localhost:3000 (直接アクセス)

### 3. Docker 構成
- **nginx**: リバースプロキシ (ポート 80)
- **PHP + Apache**: 管理画面 (ポート 8080 → nginx 経由)
- **Next.js**: アプリケーション (ポート 3000)
- **supervisor**: マルチプロセス管理

### 4. Docker ログ確認
```bash
# コンテナログ確認
docker-compose logs -f

# 特定サービスのログ
docker-compose logs -f nextjs-monitor
```

---

## 使い方 / Usage

### 1. 管理画面アクセス
```
http://your-server/
```
初回アクセス時は Digest 認証が表示されます。`.env.auth` で設定した認証情報を入力してください。

### 2. 基本操作
| ボタン | 機能 | 説明 |
|--------|------|------|
| **ビルド** | npm run build | Next.js アプリケーションをビルド |
| **起動** | npm run start | Next.js アプリケーションを起動 (ポート 3000) |
| **停止** | プロセス終了 | 実行中の Next.js プロセスを停止 |
| **再起動** | 停止 → 起動 | アプリケーションを再起動 |
| **状態確認** | プロセス確認 | 現在の実行状態をチェック |

### 3. 高度な機能
- **Git Pull**: リポジトリから最新コードを取得
- **環境変数作成**: `.env.local` ファイルを自動生成
- **リアルタイムログ**: ストリーミング形式でログを監視
- **デバッグ機能**: 詳細な実行情報を表示

### 4. 操作フロー例
1. **Git Pull** でコードを最新化
2. **環境変数作成** で設定ファイルを生成
3. **ビルド** でアプリケーションをコンパイル
4. **起動** でサービスを開始
5. **ログ確認** で動作状況を監視

### 5. ログ監視
- リアルタイムでログが自動更新
- エラーや警告は色分けして表示
- ログファイルは `logs/nextjs.log` に保存

---

## パーミッション / Permissions

### Linux/Ubuntu の場合
```bash
cd nextjs_moniter_php

# ディレクトリパーミッション設定
chmod -R 775 logs pids next-app

# 所有者設定 (Apache/nginx ユーザー)
chown -R www-data:www-data logs pids
# または nginx の場合
# chown -R nginx:nginx logs pids

# PHP プロセスに実行権限
chmod +x public/*.php
```

### macOS の場合
```bash
cd nextjs_moniter_php

# ディレクトリパーミッション設定
chmod -R 755 logs pids next-app

# 現在のユーザーで所有権設定
chown -R $(whoami):staff logs pids next-app

# 実行権限付与
chmod +x public/*.php
```

### セキュリティ考慮事項
- `.env.auth` ファイルは 600 パーミッション推奨
- Web サーバーからの書き込みが必要なのは `logs/` と `pids/` のみ
- `next-app/node_modules/` は適切にアクセス制御

---

## macOS での検証 / macOS Testing

### 組み込み PHP サーバーでのテスト
```bash
cd /path/to/nextjs_moniter_php/public
php -S localhost:8080
```

### 動作確認手順
1. ブラウザで `http://localhost:8080` にアクセス
2. Digest 認証でログイン (`.env.auth` の認証情報)
3. 各機能をテスト:
   - Next.js プロジェクトの存在確認
   - ビルド動作
   - 起動・停止操作
   - ログ出力確認

### macOS 固有の注意事項
- Homebrew でインストールした PHP を使用することを推奨
- Node.js は公式サイトまたは nvm 経由でインストール
- ファイルパーミッションは一般的に緩和されている

### トラブルシューティング (macOS)
```bash
# PHP 版本確認
php -v

# Node.js 版本確認
node -v
npm -v

# プロセス確認
lsof -i :3000  # Next.js ポート確認
lsof -i :8080  # PHP サーバーポート確認
```

---

## トラブルシューティング / Troubleshooting

### よくある問題と解決方法

#### 1. 認証エラー
**問題**: ログインできない
```bash
# 解決方法
# 1. .env.auth ファイルの存在確認
ls -la .env.auth

# 2. 認証情報の確認
cat .env.auth

# 3. ファイルパーミッション確認
chmod 600 .env.auth
```

#### 2. npm インストールエラー
**問題**: EEXIST エラーや npm install が失敗する
```bash
# 解決方法
cd next-app

# 1. ローカルキャッシュクリア
rm -rf .npm-cache .tmp node_modules

# 2. 環境変数を設定して手動インストール
export TMPDIR="$(pwd)/.tmp"
export npm_config_cache="$(pwd)/.npm-cache"
mkdir -p .tmp .npm-cache
npm install --prefer-offline --no-audit --no-fund

# 3. 権限問題の場合
sudo chown -R $(whoami) .npm-cache .tmp node_modules
```

#### 3. Next.js ビルドエラー
**問題**: npm run build が失敗する
```bash
# 解決方法
cd next-app

# 1. 依存関係の再インストール
rm -rf node_modules package-lock.json .npm-cache
npm install

# 2. キャッシュクリア
npm run clean  # (あれば)
rm -rf .next

# 3. Node.js バージョン確認
node -v  # 18+ 必要
```

#### 3. ポート衝突エラー
**問題**: ポート 3000 が使用中
```bash
# 解決方法
# 1. 使用中プロセス確認
lsof -i :3000

# 2. プロセス終了
kill -9 <PID>

# 3. 自動停止機能使用
# 管理画面の「停止」ボタンを使用
```

#### 4. Docker 関連エラー
**問題**: ContainerConfig エラー
```bash
# 解決方法
# 1. Docker Compose ファイル確認
docker-compose config

# 2. イメージ再ビルド
docker-compose down
docker-compose up --build --force-recreate

# 3. ボリューム確認
docker volume ls
```

#### 5. ファイルパーミッションエラー
**問題**: ログファイル書き込み不可
```bash
# 解決方法
# 1. ディレクトリパーミッション修正
chmod 775 logs pids

# 2. 所有者修正 (Linux)
chown -R www-data:www-data logs pids

# 3. SELinux 無効化 (CentOS/RHEL)
setsebool -P httpd_exec_enable 1
```

### ログの確認方法
```bash
# PHP エラーログ
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # nginx

# アプリケーションログ
tail -f logs/nextjs.log

# Docker ログ
docker-compose logs -f nextjs-monitor
```

### デバッグ機能の使用
管理画面の「デバッグ情報」ボタンで以下の情報を確認:
- PHP 環境情報
- ディレクトリ構造
- プロセス状態
- 環境変数
- Git 状態

---

