# Next.js 監視・デプロイ管理ツール（PHP + nginx + Apache）

## 概要

Dockerコンテナ内で、以下をまとめて動かす運用ツールです。

- 管理画面から「ビルド・記事の公開操作」を実行
- リバースプロキシで Next.jsのコンテンツを配信

管理画面の主な操作（public/index.php）:

 - 記事更新・ビルド・公開（action: `deploy`）
   - `.env` → `next-app/.env.local` をコピー
   - `next-app` 配下をクリーンアップ（`.next` / `node_modules` 等）
   - `npm install` → `npm run build` → Next.js 起動 → nginx 再起動
- nginx再起動（action: `restart`）
   - nginx のみ再起動（Next.js は継続稼働）
- nginx停止（action: `stop`）
   - nginx のみ停止（Next.js は `http://localhost:3000/` で直アクセス可能）
- 状態確認（action: `status`）
   - PID/プロセス/ポート/疎通/最新ログを表示

補足: nginx と Apache は supervisord 管理ですが、Next.js は supervisord 管理ではありません。

## 構成

### コンテナとポート

- コンテナ名: `nextjs-monitor-php-new`
- 公開ポート（docker-compose.yml）
   - `80:80`（nginx → Next.js）
   - `8080:8080`（Apache/PHP 管理画面 直）
   - `3000:3000`（Next.js 直アクセス用・任意）

### 主要ファイル

- docker-compose.yml: ポート、ボリューム、環境変数など
- Dockerfile: `php:8.3-apache` ベース + nginx/supervisor/Node.js(20) を同居
- nginx.conf: `/:3000`、`/ig/:host.docker.internal:8000`、`/admin:8080` のプロキシ設定
- supervisord.conf: nginx と Apache を常駐起動
- public/api.php: 各アクション（deploy/restart/stop/status 等）の処理本体
- public/auth.php: 管理画面/API の Digest 認証

### データの置き場所

- `/var/www/html/next-app`（Next.js プロジェクト）
- `/var/www/html/logs`（例: `logs/nextjs.log`）
- `/var/www/html/pids`（例: `pids/nextjs.pid`）

注意: このリポジトリ自体は Next.js 本体を同梱しない前提で、`/var/www/html/next-app/package.json` が存在する必要があります。

### 認証（Digest 認証）

- 設定ファイル: `.env.auth`（コンテナ内では `/var/www/html/.env.auth`）
- 設定キー: `AUTH_USERNAME` / `AUTH_PASSWORD` / `AUTH_REALM`

### ルーティング注意点

- `http://localhost:8080/`（管理画面・推奨）
- `http://localhost/admin`（nginx 経由）
   - nginx の `/admin` は URI を書き換えません。Apache 側に `/admin` ルーティングが無い場合は 404 になります。

## dockerコンテナの立て方

### 1) 初期ファイルを用意

```bash
cd nextjs_moniter_php

cp .env.example .env
cp .env.auth.example .env.auth
chmod 600 .env.auth
```

### 2) 起動

```bash
docker compose up -d --build
```

### 3) アクセス

- 公開サイト（nginx → Next.js）: `http://localhost/`（80）
- 管理画面（Apache 直）: `http://localhost:8080/`（8080）
- Next.js 直（任意）: `http://localhost:3000/`（3000）

### 4) Next.js プロジェクトを配置（初回のみ）

Next.js が未配置の場合、管理画面のビルドは失敗します（`next-app/package.json` が無いため）。

例: コンテナ内で clone する場合

```bash
docker exec -it nextjs-monitor-php-new bash
cd /var/www/html
rm -rf next-app
git clone https://github.com/AIM-SC/next-website.git next-app
exit
```

補足: docker-compose.yml の `GITURL` は API の `Renewal` アクション（GitPull）で参照されます（ただし現状 UI にはボタン非表示）。

### 5) ログ/状態確認

```bash
docker compose logs -f nextjs-monitor
docker exec -it nextjs-monitor-php-new tail -f /var/www/html/logs/nextjs.log
docker exec -it nextjs-monitor-php-new supervisorctl status
```

## 脆弱性対応

このリポジトリの「脆弱性対応」は大きく 3 つ（OS/ランタイム、Node 依存、運用設定）に分かれます。

### 1) OS・ベースイメージの更新（Debian/PHP/Apache/nginx 等）

- ベースイメージは `php:8.3-apache` です。CVE 対応は「最新イメージで再ビルド」が基本になります。
- 例（ローカルで最新を取り込んでビルド）:


```bash
docker compose build --pull
docker compose up -d
```

### 2) Next.js 依存関係（npm）の更新・監査

- `deploy` は `npm install` と `npm run build` を行いますが、依存関係の“更新”までは行いません。
- 監査例（コンテナ内で実行）:

```bash
docker exec -it nextjs-monitor-php-new bash -lc 'cd /var/www/html/next-app && npm audit --production'
```

- 更新例（影響が大きいので、まず開発環境で検証推奨）:

```bash
docker exec -it nextjs-monitor-php-new bash -lc 'cd /var/www/html/next-app && npm update'
```

### 3) イメージスキャン（任意）

環境に応じて、以下のいずれかでコンテナイメージの CVE スキャンを実施します。

- Docker Scout（利用できる場合）: `docker scout cves <image>`
- Trivy（利用できる場合）: `trivy image <image>`

### 4) 運用上のセキュリティ注意

- 認証情報
   - `.env.auth` のデフォルト値のまま運用しない（必ず変更）
   - `.env` / `.env.auth` は機密情報を含むため、権限と配布方法を管理する
- 露出ポート
   - `3000:3000` は「直アクセス用」です。不要なら公開しない（compose 側でポート公開を外す）
- 権限
   - 現状の compose は `user: "0:0"` と `privileged: true` で起動します。運用環境では最小権限化（privileged の撤廃等）を検討してください。




