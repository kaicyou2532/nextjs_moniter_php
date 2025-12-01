# Next.js PHP 管理ツール

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
define('BASE_DIR', '/path/to/nextjs_moniter_php');
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
---

## 環境変数設定 / Environment Variables

### Next.js 環境変数の管理
`.env` ファイルで Next.js の環境変数を管理します。デプロイ時に自動的に `next-app/.env.local` にコピーされます。

### 設定手順
1. `.env.example` をコピーして `.env` ファイルを作成:
```bash
cp .env.example .env
```

2. `.env` ファイルを編集して実際の値を設定:
```bash
# MicroCMS Settings
MICROCMS_SERVICE_DOMAIN=learning-commons
MICROCMS_API_KEY=your-actual-api-key
MICROCMS_PREVIEW_SECRET=your-preview-secret

# Google Analytics
GA_ID=G-XXXXXXXXXX
```

3. 管理画面の「🚀 デプロイ」ボタンをクリック
   - 自動的に `.env` → `next-app/.env.local` にコピーされます
   - ビルドと起動が実行されます

### 環境変数の内容
| 変数名 | 説明 | 例 |
|--------|------|-----|
| `MICROCMS_SERVICE_DOMAIN` | MicroCMSのサービスドメイン | `learning-commons` |
| `MICROCMS_API_KEY` | MicroCMS APIキー | `your-api-key` |
| `MICROCMS_PREVIEW_SECRET` | プレビュー用シークレット | `preview-secret` |
| `GA_ID` | Google Analytics ID | `G-XXXXXXXXXX` |
| `NODE_ENV` | 実行環境 | `production` |

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
| **🚀 デプロイ** | Git更新 → 環境変数設定 → ビルド → 起動 → nginx再起動 | すべてを自動実行 |
| **🔄 Webサーバー再起動** | 停止 → 起動 (npm run start) | ビルド済みアプリを再起動 |
| **⚠️ Webサーバー停止** | Next.jsアプリ停止 | アプリケーションを停止 |
| **📊 状態確認** | プロセス確認 | 現在の実行状態をチェック |

### 3. デプロイの流れ
「🚀 デプロイ」ボタンをクリックすると、以下の処理が自動実行されます：

1. **GitHubから最新版を取得** - リポジトリから最新コードをプル
2. **環境変数を設定** - `.env` → `next-app/.env.local` にコピー
3. **ビルド実行** - `npm run build` でアプリをコンパイル
4. **アプリケーション起動** - Next.jsサーバーを起動
5. **リバースプロキシ再起動** - nginxを再起動

### 4. 初回セットアップ

**ローカル環境の場合:**
```bash
# 1. プロジェクトルートに .env ファイルを作成
cd /path/to/nextjs_moniter_php
cp .env.example .env

# 2. 実際の値を設定
nano .env
# または
cat > .env << 'EOF'
MICROCMS_SERVICE_DOMAIN=learning-commons
MICROCMS_API_KEY=your-actual-api-key
MICROCMS_PREVIEW_SECRET=your-preview-secret
GA_ID=G-XXXXXXXXXX
NODE_ENV=production
GITURL=https://github.com/AIM-SC/next-website.git
EOF

# 3. 管理画面にアクセスしてデプロイ
```

**Docker環境の場合:**
```bash
# 1. ホスト側で .env ファイルを作成
cp .env.example .env
nano .env  # 実際の値を設定

# 2. コンテナを起動（.envは自動的にマウントされます）
docker-compose up -d

# 3. 管理画面にアクセス
http://your-server/

# 4. デプロイボタンをクリック
```

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

#### 4. ChunkLoadError - Next.jsファイル読み込みエラー
**問題**: ブラウザコンソールに `ChunkLoadError: Loading chunk XXX failed` が表示される
```
ChunkLoadError: Loading chunk 334 failed.
(error: http://example.com/_next/static/chunks/...)
```

**原因**:
- Next.jsのビルドキャッシュの不整合
- デプロイ中のアクセス
- ブラウザキャッシュの古いファイル
- nginx のキャッシュ設定

**解決方法**:

1. **再デプロイ** 
   ```bash
   # 管理画面で「🚀 記事更新・ビルド・公開」ボタンをクリック
   # 以下が自動実行されます:
   # - Git更新
   # - 環境変数設定
   # - キャッシュクリア (.next, node_modules/.cache, npm cache)
   # - ビルド実行
   # - Next.js完全再起動 (既存プロセス強制終了 → 新規起動)
   # - nginx再起動
   ```

2. **ブラウザキャッシュクリア**
   ```bash
   # Chrome/Edge: Ctrl + Shift + R (Mac: Cmd + Shift + R)
   # Firefox: Ctrl + F5 (Mac: Cmd + Shift + R)
   # または、シークレット/プライベートモードで開く
   ```

3. **手動での完全クリーンアップ** (Dockerコンテナ内)
   ```bash
   # コンテナに入る
   docker exec -it nextjs-monitor-php-new bash
   
   # Next.jsディレクトリに移動
   cd /var/www/html/next-app
   
   # キャッシュ完全削除
   rm -rf .next node_modules/.cache .npm-cache .tmp
   npm cache clean --force
   
   # 依存関係再インストール
   export TMPDIR="$(pwd)/.tmp"
   export npm_config_cache="$(pwd)/.npm-cache"
   mkdir -p .tmp .npm-cache
   npm install
   
   # 再ビルド
   npm run build
   
   # プロセス停止
   pkill -9 -f "next start"
   fuser -k 3000/tcp
   
   # 再起動
   npm run start
   
   exit
   
   # nginx再起動
   # 管理画面で「🔄 nginx再起動」ボタンをクリック
   ```

4. **nginx設定の確認**
   ```bash
   # nginx設定にキャッシュ制御が含まれているか確認
   docker exec -it nextjs-monitor-php-new nginx -t
   docker exec -it nextjs-monitor-php-new cat /etc/nginx/nginx.conf
   ```

**予防策**:
- デプロイ時は必ず「🚀 記事更新・ビルド・公開」を使用（キャッシュクリア機能付き）
- ブラウザで開発者ツールの「Disable cache」を有効化して動作確認
- nginx設定で `/_next/static/` のキャッシュ制御が適切に設定されていることを確認

#### 5. Docker 関連エラー
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

#### 5. Docker 関連エラー
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

#### 6. ファイルパーミッションエラー
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

