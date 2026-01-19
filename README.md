# Next.js / PHP 管理ツール

Next.js サイトと、デプロイ・ログ確認・再起動などを行う PHP 管理画面を 1 コンテナで運用するためのツールです。

- 公開サイト（nginx → Next.js）: `http://<host>/`（ポート `80`）
- 管理画面（nginx 経由）: `http://<host>/admin`（ポート `80`）
- 管理画面（Apache 直）: `http://<host>:8080/`（ポート `8080`）
- Next.js 直アクセス（任意）: `http://<host>:3000/`（ポート `3000`）

## まずは Docker（推奨）

この構成では Next.js の実体はコンテナ内の `/var/www/html/next-app` に置きます。
`docker-compose.yml` は `nextjs-app-data` という名前付きボリュームを使うため、ホスト側の `next-app/` にソースが無くても動きます（初回デプロイ時に `GITURL` から取得）。

```bash
cd nextjs_moniter_php

# 1) Next.js 用の環境変数（必要な値に差し替え）
cp .env.example .env

# 2) 管理画面の Digest 認証（必要な値に差し替え）
cp .env.auth.example .env.auth
chmod 600 .env.auth

# 3) 起動（Compose v2）
docker compose up -d --build

# 旧 docker-compose の場合はこちら
# docker-compose up -d --build
```

## ディレクトリ構成

```text
nextjs_moniter_php/
├── public/                 # 管理画面（Apacheで配信）
│   ├── index.php
│   ├── api.php
│   └── auth.php
├── next-app/                # Next.js 配置先（Dockerでは主にボリューム側を使用）
├── logs/                    # 管理ツールのログ
├── pids/                    # Next.js PID
├── .env                     # Next.js 環境変数（コンテナへ read-only マウント）
├── .env.example
├── .env.auth                # Digest 認証
├── .env.auth.example
├── docker-compose.yml
├── Dockerfile
├── nginx.conf
├── supervisord.conf
├── DOCKER.md                # Docker運用メモ
└── *.sh                     # 運用補助スクリプト
```

## 主要な設定

### `.env`（Next.js 環境変数 + 管理ツール設定）

- `.env` はコンテナ内の `/var/www/html/.env` にマウントされ、デプロイ時に `next-app/.env.local` へコピーされます。
- Next.js の取得元は `GITURL` を使います（`public/api.php` は `GITURL` が無ければデフォルト値を使用）。

### `.env.auth`（Digest 認証）

- 管理画面の認証情報を設定します（`AUTH_USERNAME` / `AUTH_PASSWORD` / `AUTH_REALM`）。
- 本番では `chmod 600 .env.auth` を推奨します。

## Docker 構成（現状）

- 1 コンテナ内で `Apache(8080)` / `nginx(80)` / `Node(3000)` を `supervisord` で管理
- コンテナ名: `nextjs-monitor-php-new`
- サービス名（compose）: `nextjs-monitor`

## 運用コマンド（本番向け）

### 起動・停止

```bash
docker compose up -d --build
docker compose down
```

### ログ

```bash
# composeログ
docker compose logs -f nextjs-monitor

# 管理ツールのアプリログ（logs-data ボリューム内）
docker exec -it nextjs-monitor-php-new tail -f /var/www/html/logs/nextjs.log
```

### コンテナ内に入る

```bash
docker exec -it nextjs-monitor-php-new bash
```

### バージョン確認

```bash
# Node.js
docker exec -it nextjs-monitor-php-new node -v

# Next.js（インストール済みの実体）
docker exec -it nextjs-monitor-php-new bash -lc 'cd /var/www/html/next-app && npm ls next'
```

## よくある詰まりどころ

### コンテナ名を間違える

`docker exec` は `docker ps` の `NAMES` を指定してください。

```bash
docker ps
docker exec -it nextjs-monitor-php-new node -v
```

### 「最新コードが反映されない」

Docker では `nextjs-app-data`（名前付きボリューム）に Next.js が保持されます。
完全に作り直したい場合はボリュームも削除します（データが消えるので注意）。

```bash
docker compose down
docker volume rm nextjs_moniter_php_nextjs-app-data 2>/dev/null || true
docker compose up -d --build
```

### ビルド時の `EACCES`（`.next` 削除失敗など）

```bash
docker exec -it nextjs-monitor-php-new bash -lc 'cd /var/www/html/next-app && chmod -R 777 .next 2>/dev/null || true && rm -rf .next'
```

### `ChunkLoadError` など静的ファイル不整合

- 再デプロイ後にブラウザの強制リロード（`Cmd+Shift+R`）
- それでもダメなら `.next` を削除して再ビルド（上の `EACCES` 手順）

## ディスク容量管理

`disk-cleanup.sh` / `setup-cron.sh` / `force-cleanup.sh` を同梱しています。

```bash
chmod +x disk-cleanup.sh setup-cron.sh force-cleanup.sh
./setup-cron.sh
tail -f logs/cleanup.log
```







