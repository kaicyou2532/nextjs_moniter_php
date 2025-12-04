#!/bin/bash
#
# Cron設定スクリプト
# disk-cleanup.sh を6時間おきに実行するcronジョブを追加します
#
# 使用方法:
#   chmod +x setup-cron.sh
#   ./setup-cron.sh

set -e

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLEANUP_SCRIPT="${SCRIPT_DIR}/disk-cleanup.sh"
LOG_FILE="${SCRIPT_DIR}/logs/cleanup.log"

# クリーンアップスクリプトに実行権限を付与
chmod +x "${CLEANUP_SCRIPT}"

# cronジョブの内容
CRON_JOB="0 */6 * * * ${CLEANUP_SCRIPT} >> ${LOG_FILE} 2>&1"

echo "=========================================="
echo "Cron設定スクリプト"
echo "=========================================="
echo ""
echo "以下のcronジョブを追加します:"
echo "${CRON_JOB}"
echo ""
echo "このジョブは6時間おき（0時、6時、12時、18時）に実行されます"
echo ""

# 既存のcrontabを取得
TEMP_CRON=$(mktemp)
crontab -l > "${TEMP_CRON}" 2>/dev/null || true

# すでに同じジョブが存在するかチェック
if grep -Fq "${CLEANUP_SCRIPT}" "${TEMP_CRON}"; then
    echo "警告: 同じスクリプトのcronジョブが既に存在します"
    echo ""
    echo "既存のcronジョブ:"
    grep -F "${CLEANUP_SCRIPT}" "${TEMP_CRON}"
    echo ""
    read -p "既存のジョブを削除して新しいジョブを追加しますか? (y/N): " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # 既存のジョブを削除
        grep -vF "${CLEANUP_SCRIPT}" "${TEMP_CRON}" > "${TEMP_CRON}.new"
        mv "${TEMP_CRON}.new" "${TEMP_CRON}"
        echo "既存のジョブを削除しました"
    else
        echo "キャンセルしました"
        rm "${TEMP_CRON}"
        exit 0
    fi
fi

# 新しいcronジョブを追加
echo "${CRON_JOB}" >> "${TEMP_CRON}"

# crontabに設定
crontab "${TEMP_CRON}"
rm "${TEMP_CRON}"

echo ""
echo "✓ Cronジョブを追加しました"
echo ""
echo "現在のcrontab設定:"
echo "=========================================="
crontab -l
echo "=========================================="
echo ""
echo "【手動実行でテストする場合】"
echo "  ${CLEANUP_SCRIPT}"
echo ""
echo "【ログを確認する場合】"
echo "  tail -f ${LOG_FILE}"
echo ""
echo "【cronジョブを削除する場合】"
echo "  crontab -e"
echo "  # エディタで該当行を削除"
echo ""
echo "設定完了！"
