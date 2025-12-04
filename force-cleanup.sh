#!/bin/bash
#
# 強制クリーンアップスクリプト
# ディスク容量チェックなしで、すぐにクリーンアップを実行します
#
# 使用方法:
#   chmod +x force-cleanup.sh
#   ./force-cleanup.sh

set -e

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=========================================="
echo "強制クリーンアップ"
echo "=========================================="
echo ""
echo "以下の操作を実行します:"
echo "  1. Dockerビルドキャッシュの削除"
echo "  2. Next.jsビルドデータの削除"
echo "  3. 古いログファイルの削除"
echo ""
read -p "続行しますか? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "キャンセルしました"
    exit 0
fi

# disk-cleanup.sh を強制実行モードで実行
export THRESHOLD_MB=999999  # 閾値を非常に大きくして必ず実行されるようにする

"${SCRIPT_DIR}/disk-cleanup.sh"

echo ""
echo "強制クリーンアップ完了！"
