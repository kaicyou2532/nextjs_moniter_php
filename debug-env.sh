#!/bin/bash
#
# 環境変数とMicroCMS API接続のデバッグスクリプト
#

echo "=========================================="
echo "環境変数デバッグ"
echo "=========================================="
echo ""

# 1. ホスト側の .env 確認
echo "=== 1. ホスト側 .env ファイル ==="
if [ -f ".env" ]; then
    echo "✅ .env ファイルが存在します"
    echo ""
    echo "内容（APIキーはマスク）:"
    cat .env | grep -v "^#" | grep -v "^$" | sed 's/\(API_KEY=\).*/\1***(マスク)***/'
else
    echo "❌ .env ファイルが存在しません"
fi
echo ""

# 2. Docker内の .env.local 確認
echo "=== 2. Docker内 next-app/.env.local ファイル ==="
docker exec -it nextjs-monitor-php-new bash -c '
if [ -f "/var/www/html/next-app/.env.local" ]; then
    echo "✅ .env.local ファイルが存在します"
    echo ""
    echo "内容（APIキーはマスク）:"
    cat /var/www/html/next-app/.env.local | grep -v "^#" | grep -v "^$" | sed "s/\(API_KEY=\).*/\1***(マスク)***/"
else
    echo "❌ .env.local ファイルが存在しません"
fi
'
echo ""

# 3. 必須変数チェック
echo "=== 3. 必須環境変数チェック ==="
docker exec -it nextjs-monitor-php-new bash -c '
REQUIRED_VARS="MICROCMS_SERVICE_DOMAIN MICROCMS_API_KEY"
ENV_FILE="/var/www/html/next-app/.env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env.local が存在しません"
    exit 1
fi

for VAR in $REQUIRED_VARS; do
    if grep -q "^${VAR}=" "$ENV_FILE"; then
        VALUE=$(grep "^${VAR}=" "$ENV_FILE" | cut -d "=" -f2)
        if [ -z "$VALUE" ]; then
            echo "❌ $VAR: 未設定（空）"
        else
            echo "✅ $VAR: 設定済み"
        fi
    else
        echo "❌ $VAR: 存在しない"
    fi
done
'
echo ""

# 4. MicroCMS API接続テスト
echo "=== 4. MicroCMS API接続テスト ==="
docker exec -it nextjs-monitor-php-new bash -c '
ENV_FILE="/var/www/html/next-app/.env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env.local が存在しないため、APIテストをスキップします"
    exit 1
fi

SERVICE_DOMAIN=$(grep "^MICROCMS_SERVICE_DOMAIN=" "$ENV_FILE" | cut -d "=" -f2)
API_KEY=$(grep "^MICROCMS_API_KEY=" "$ENV_FILE" | cut -d "=" -f2)

if [ -z "$SERVICE_DOMAIN" ] || [ -z "$API_KEY" ]; then
    echo "❌ MICROCMS_SERVICE_DOMAIN または MICROCMS_API_KEY が設定されていません"
    exit 1
fi

echo "サービスドメイン: $SERVICE_DOMAIN"
echo "APIキー: ${API_KEY:0:10}...（先頭10文字のみ表示）"
echo ""

# blog エンドポイントをテスト
echo "📡 GET https://${SERVICE_DOMAIN}.microcms.io/api/v1/blog?limit=1"
echo ""

RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}" \
    -H "X-MICROCMS-API-KEY: $API_KEY" \
    "https://${SERVICE_DOMAIN}.microcms.io/api/v1/blog?limit=1")

HTTP_STATUS=$(echo "$RESPONSE" | grep "HTTP_STATUS:" | cut -d ":" -f2)
BODY=$(echo "$RESPONSE" | sed "/HTTP_STATUS:/d")

echo "ステータスコード: $HTTP_STATUS"
echo ""

case $HTTP_STATUS in
    200)
        echo "✅ API接続成功！"
        echo ""
        echo "レスポンス（最初の500文字）:"
        echo "$BODY" | head -c 500
        echo ""
        ;;
    401)
        echo "❌ 401 Unauthorized - APIキーが無効です"
        echo "MicroCMSダッシュボードでAPIキーを確認してください"
        ;;
    403)
        echo "❌ 403 Forbidden - APIキーの権限が不足しています"
        echo "APIキーに「コンテンツの取得」権限があるか確認してください"
        ;;
    404)
        echo "❌ 404 Not Found - サービスドメインまたはエンドポイントが間違っています"
        echo ""
        echo "確認事項:"
        echo "1. サービスドメイン: $SERVICE_DOMAIN が正しいか"
        echo "2. エンドポイント: blog が存在するか"
        echo ""
        echo "MicroCMSダッシュボードで確認してください:"
        echo "https://$SERVICE_DOMAIN.microcms.io/"
        ;;
    *)
        echo "❌ エラー: HTTP $HTTP_STATUS"
        echo ""
        echo "レスポンス:"
        echo "$BODY"
        ;;
esac
'
echo ""

echo "=========================================="
echo "デバッグ完了"
echo "=========================================="
