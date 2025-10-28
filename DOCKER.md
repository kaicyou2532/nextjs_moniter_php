# Docker起動用README

## 使用方法

### 1. 環境設定
```bash
# .envファイルを作成（オプション）
cp .env.example .env
# 必要に応じてGITURL等を設定
```

### 2. Dockerビルド & 起動
```bash
# 既存のコンテナを停止・削除（存在する場合）
docker-compose down

# Docker Composeでビルド・起動
docker-compose up -d --build

# または、Dockerコマンドで直接ビルド・起動
docker build -t nextjs-monitor-php .
docker run -d -p 8080:80 -p 3000:3000 \
  -v $(pwd)/next-app:/var/www/html/next-app \
  -v $(pwd)/logs:/var/www/html/logs \
  -v $(pwd)/pids:/var/www/html/pids \
  --name nextjs-monitor-php \
  nextjs-monitor-php
```

### 3. アクセス
- PHP管理ツール: http://localhost:8080
- Next.jsアプリ: http://localhost:3000 (起動後)

### 4. 停止・削除
```bash
# 停止
docker-compose down

# または
docker stop nextjs-monitor-php
docker rm nextjs-monitor-php

# イメージも削除する場合
docker rmi nextjs-monitor-php
```

### 5. ログ確認
```bash
# コンテナログを確認
docker-compose logs -f

# または
docker logs -f nextjs-monitor-php

# 特定のサービスのログ
docker exec nextjs-monitor-php tail -f /var/log/supervisor/apache2.log
docker exec nextjs-monitor-php tail -f /var/log/supervisor/nginx.log
```

## 認証情報
- ユーザー名: admin
- パスワード: aimgstaff

## 技術構成
- **PHP 8.3 + Apache**: Web管理ツール
- **Node.js 20.x**: Next.jsアプリ実行環境
- **nginx**: リバースプロキシ（ポート3000）
- **Supervisor**: マルチプロセス管理

## 注意事項
- コンテナ内でNext.jsアプリのビルド・起動を行うため、初回は時間がかかります
- nginxの制御にはsupervisorctlを使用しています
- ログとPIDファイルはホストにマウントされます
- Docker接続エラーを回避するため、privilegedモードは無効化されています

## トラブルシューティング

### ポートが使用中の場合
```bash
# ポート使用状況確認
lsof -i :8080
lsof -i :3000

# 使用中のプロセスを停止してから再実行
```

### コンテナ内でのデバッグ
```bash
# コンテナにログイン
docker exec -it nextjs-monitor-php bash

# サービス状況確認
supervisorctl status

# nginxの設定テスト
nginx -t
```
