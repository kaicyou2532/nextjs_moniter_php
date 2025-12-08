#!/bin/bash
#
# Next.js環境変数確認スクリプト
# Next.jsアプリケーションが環境変数を正しく読み込めているかチェックします
#

echo "=========================================="
echo "Next.js 環境変数確認"
echo "=========================================="
echo ""

# Docker環境での確認
echo "=== 1. 環境変数ファイルの存在確認 ==="
docker exec -it nextjs-monitor-php-new bash -c '
echo "プロジェクトルート (.env):"
if [ -f "/var/www/html/.env" ]; then
    echo "  ✅ /var/www/html/.env が存在します"
    echo "  行数: $(cat /var/www/html/.env | grep -v "^#" | grep -v "^$" | wc -l)"
else
    echo "  ❌ /var/www/html/.env が存在しません"
fi

echo ""
echo "Next.jsディレクトリ (.env.local):"
if [ -f "/var/www/html/next-app/.env.local" ]; then
    echo "  ✅ /var/www/html/next-app/.env.local が存在します"
    echo "  行数: $(cat /var/www/html/next-app/.env.local | grep -v "^#" | grep -v "^$" | wc -l)"
else
    echo "  ❌ /var/www/html/next-app/.env.local が存在しません"
fi
'
echo ""

# 環境変数の内容確認（マスク表示）
echo "=== 2. 環境変数の内容（APIキーはマスク） ==="
docker exec -it nextjs-monitor-php-new bash -c '
if [ -f "/var/www/html/next-app/.env.local" ]; then
    cat /var/www/html/next-app/.env.local | grep -v "^#" | grep -v "^$" | \
    sed "s/\(API_KEY=\).*/\1***(マスク)***/" | \
    sed "s/\(SECRET=\).*/\1***(マスク)***/"
else
    echo "  ファイルが存在しません"
fi
'
echo ""

# 必須環境変数のチェック
echo "=== 3. 必須環境変数チェック ==="
docker exec -it nextjs-monitor-php-new bash -c '
REQUIRED_VARS=(
    "MICROCMS_SERVICE_DOMAIN"
    "MICROCMS_API_KEY"
    "NEXT_PUBLIC_API_URL"
)

ENV_FILE="/var/www/html/next-app/.env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env.local が存在しません"
    exit 1
fi

ALL_OK=true

for VAR in "${REQUIRED_VARS[@]}"; do
    if grep -q "^${VAR}=" "$ENV_FILE"; then
        VALUE=$(grep "^${VAR}=" "$ENV_FILE" | cut -d "=" -f2)
        if [ -z "$VALUE" ]; then
            echo "❌ $VAR: 未設定（空）"
            ALL_OK=false
        else
            echo "✅ $VAR: 設定済み"
        fi
    else
        echo "❌ $VAR: 存在しない"
        ALL_OK=false
    fi
done

echo ""
if [ "$ALL_OK" = true ]; then
    echo "✅ すべての必須環境変数が設定されています"
else
    echo "❌ 一部の環境変数が未設定です"
fi
'
echo ""

# Next.jsビルド時の環境変数読み込み確認
echo "=== 4. Next.jsビルド時の環境変数読み込み確認 ==="
echo "ビルドログから環境変数の読み込み状況を確認します..."
echo ""

docker exec -it nextjs-monitor-php-new bash -c '
cd /var/www/html/next-app

# package.jsonの確認
if [ ! -f "package.json" ]; then
    echo "❌ package.json が存在しません"
    exit 1
fi

echo "📦 Next.jsバージョン:"
grep "\"next\":" package.json | head -1

echo ""
echo "📋 環境変数読み込みテスト（ビルドの最初の20行）:"
echo "---"

# ビルドの最初の部分だけを実行して環境変数読み込みを確認
npm run build 2>&1 | head -30 | grep -A 5 "Environments:" || echo "環境変数読み込み情報が見つかりません"

echo ""
echo "注意: ビルドは途中で停止しています（確認のみ）"
'
echo ""

# Node.js環境での環境変数確認
echo "=== 5. Node.jsランタイムでの環境変数確認 ==="
docker exec -it nextjs-monitor-php-new bash -c '
cd /var/www/html/next-app

# 簡易的なNode.jsスクリプトで環境変数を確認
node -e "
const fs = require('\''fs'\'');
const path = require('\''path'\'');

console.log('\''📍 現在のディレクトリ:'\'', process.cwd());
console.log('\''\''\'');

// .env.local ファイルを読み込む
const envPath = path.join(process.cwd(), '\''.env.local'\'');
if (fs.existsSync(envPath)) {
    console.log('\''✅ .env.local ファイルが存在します'\'');
    
    // ファイルを手動で読み込んで解析
    const content = fs.readFileSync(envPath, '\''utf8'\'');
    const lines = content.split('\'\\n'\'');
    const envVars = {};
    
    lines.forEach(line => {
        if (line && !line.startsWith('\''#'\'') && line.includes('\''='\'')) {
            const [key, ...valueParts] = line.split('\''='\'');
            envVars[key.trim()] = valueParts.join('\''='\'').trim();
        }
    });
    
    console.log('\''\''\'');
    console.log('\''🔑 読み込まれた環境変数:'\'');
    console.log('\''  MICROCMS_SERVICE_DOMAIN:'\'', envVars.MICROCMS_SERVICE_DOMAIN || '\''(未設定)'\'');
    console.log('\''  MICROCMS_API_KEY:'\'', envVars.MICROCMS_API_KEY ? '\''(設定済み - '\'' + envVars.MICROCMS_API_KEY.length + '\''文字)'\'' : '\''(未設定)'\'');
    console.log('\''  NEXT_PUBLIC_API_URL:'\'', envVars.NEXT_PUBLIC_API_URL || '\''(未設定)'\'');
    console.log('\''  GA_ID:'\'', envVars.GA_ID || '\''(未設定)'\'');
    console.log('\''\''\'');
    
    // MicroCMS設定の妥当性チェック
    if (envVars.MICROCMS_SERVICE_DOMAIN && envVars.MICROCMS_API_KEY) {
        console.log('\''✅ MicroCMS基本設定は正常です'\'');
        console.log('\''   API URL: https://'\'' + envVars.MICROCMS_SERVICE_DOMAIN + '\''.microcms.io/api/v1/'\'');
    } else {
        console.log('\''❌ MicroCMS設定が不完全です'\'');
    }
} else {
    console.log('\''❌ .env.local ファイルが見つかりません'\'');
}
" 2>&1
'
echo ""

# MicroCMS APIテスト
echo "=== 6. MicroCMS API接続テスト ==="
docker exec -it nextjs-monitor-php-new bash -c '
cd /var/www/html/next-app

ENV_FILE=".env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env.local が存在しないため、APIテストをスキップします"
    exit 0
fi

SERVICE_DOMAIN=$(grep "^MICROCMS_SERVICE_DOMAIN=" "$ENV_FILE" | cut -d "=" -f2)
API_KEY=$(grep "^MICROCMS_API_KEY=" "$ENV_FILE" | cut -d "=" -f2)

if [ -z "$SERVICE_DOMAIN" ] || [ -z "$API_KEY" ]; then
    echo "❌ MICROCMS_SERVICE_DOMAIN または MICROCMS_API_KEY が設定されていません"
    exit 0
fi

echo "🔗 サービスドメイン: $SERVICE_DOMAIN"
echo "🔑 APIキー: ${API_KEY:0:15}...（先頭15文字のみ表示）"
echo ""

# blog エンドポイントをテスト
echo "📡 テスト: GET https://${SERVICE_DOMAIN}.microcms.io/api/v1/blog?limit=1"
echo ""

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "X-MICROCMS-API-KEY: $API_KEY" \
    "https://${SERVICE_DOMAIN}.microcms.io/api/v1/blog?limit=1")

echo "ステータスコード: $HTTP_CODE"
echo ""

case $HTTP_CODE in
    200)
        echo "✅ API接続成功！環境変数は正しく機能しています"
        ;;
    401)
        echo "❌ 401 Unauthorized - APIキーが無効です"
        ;;
    403)
        echo "❌ 403 Forbidden - APIキーの権限が不足しています"
        ;;
    404)
        echo "❌ 404 Not Found - エンドポイントが存在しません"
        echo ""
        echo "考えられる原因:"
        echo "  1. サービスドメイン名が間違っている"
        echo "  2. エンドポイント名 '\''blog'\'' が存在しない"
        echo ""
        echo "MicroCMSダッシュボードで確認してください:"
        echo "  https://$SERVICE_DOMAIN.microcms.io/"
        ;;
    000)
        echo "❌ 接続失敗 - ネットワークエラーまたはDNS解決失敗"
        ;;
    *)
        echo "❌ エラー: HTTP $HTTP_CODE"
        ;;
esac
'
echo ""

echo "=========================================="
echo "確認完了"
echo "=========================================="
echo ""
echo "次のステップ:"
echo "  1. すべての環境変数が✅なら、ビルドを実行してください"
echo "  2. ❌がある場合は、.envファイルを修正して再デプロイしてください"
echo "  3. API接続が404の場合は、MicroCMSダッシュボードでエンドポイント名を確認してください"
