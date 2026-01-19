# Docker 運用メモ

## 起動

```bash
cd nextjs_moniter_php

cp .env.example .env
cp .env.auth.example .env.auth
chmod 600 .env.auth

docker compose up -d --build
# 旧環境: docker-compose up -d --build
```

## アクセス

- 公開サイト（nginx→Next.js）: `http://localhost/`（`80`）
- 管理画面（nginx 経由）: `http://localhost/admin`（`80`）
- 管理画面（Apache 直）: `http://localhost:8080/`（`8080`）
- Next.js 直（任意）: `http://localhost:3000/`（`3000`）

## 停止

```bash
docker compose down
```

## ログ

```bash
docker compose logs -f nextjs-monitor
docker exec -it nextjs-monitor-php-new tail -f /var/www/html/logs/nextjs.log
```

## コンテナ操作

```bash
# コンテナ名は docker ps の NAMES を使う
docker ps

docker exec -it nextjs-monitor-php-new bash
supervisorctl status
nginx -t
```

## バージョン確認

```bash
docker exec -it nextjs-monitor-php-new node -v
docker exec -it nextjs-monitor-php-new bash -lc 'cd /var/www/html/next-app && npm ls next'
```
