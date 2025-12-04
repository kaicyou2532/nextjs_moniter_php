#!/bin/bash
#
# ディスク容量監視 & 自動クリーンアップスクリプト
# ディスク空き容量が500MB以下の場合、不要なファイルを削除します
#
# 使用方法:
#   chmod +x disk-cleanup.sh
#   ./disk-cleanup.sh
#
# cronで6時間おきに実行:
#   0 */6 * * * /path/to/nextjs_moniter_php/disk-cleanup.sh >> /path/to/nextjs_moniter_php/logs/cleanup.log 2>&1

set -e

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${SCRIPT_DIR}/logs/cleanup.log"
THRESHOLD_MB=500

# ログディレクトリ作成
mkdir -p "${SCRIPT_DIR}/logs"

# ログ出力関数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

# ディスク使用状況を取得（MB単位）
get_available_space_mb() {
    # macOSとLinux両対応
    if [[ "$(uname)" == "Darwin" ]]; then
        # macOS: df -k で KB単位、1024で割ってMB
        df -k "${SCRIPT_DIR}" | awk 'NR==2 {print int($4/1024)}'
    else
        # Linux: df -BM で MB単位
        df -BM "${SCRIPT_DIR}" | awk 'NR==2 {print int($4)}'
    fi
}

# Dockerビルドキャッシュのクリーンアップ
cleanup_docker_build_cache() {
    log "Dockerビルドキャッシュをクリーンアップ中..."
    
    if command -v docker &> /dev/null; then
        # ビルドキャッシュ削除
        docker builder prune -f 2>&1 | tee -a "${LOG_FILE}"
        
        # 未使用のイメージ削除
        docker image prune -f 2>&1 | tee -a "${LOG_FILE}"
        
        # 未使用のコンテナ削除
        docker container prune -f 2>&1 | tee -a "${LOG_FILE}"
        
        log "Dockerビルドキャッシュのクリーンアップ完了"
    else
        log "Dockerコマンドが見つかりません（スキップ）"
    fi
}

# Next.jsビルドデータのクリーンアップ
cleanup_nextjs_build() {
    log "Next.jsビルドデータをクリーンアップ中..."
    
    NEXT_APP_DIR="${SCRIPT_DIR}/next-app"
    
    if [ -d "${NEXT_APP_DIR}" ]; then
        # .next ディレクトリ削除
        if [ -d "${NEXT_APP_DIR}/.next" ]; then
            log "削除中: ${NEXT_APP_DIR}/.next"
            rm -rf "${NEXT_APP_DIR}/.next"
        fi
        
        # node_modules ディレクトリ削除
        if [ -d "${NEXT_APP_DIR}/node_modules" ]; then
            log "削除中: ${NEXT_APP_DIR}/node_modules"
            rm -rf "${NEXT_APP_DIR}/node_modules"
        fi
        
        # .next-cache ディレクトリ削除
        if [ -d "${NEXT_APP_DIR}/.next-cache" ]; then
            log "削除中: ${NEXT_APP_DIR}/.next-cache"
            rm -rf "${NEXT_APP_DIR}/.next-cache"
        fi
        
        # package-lock.json 削除
        if [ -f "${NEXT_APP_DIR}/package-lock.json" ]; then
            log "削除中: ${NEXT_APP_DIR}/package-lock.json"
            rm -f "${NEXT_APP_DIR}/package-lock.json"
        fi
        
        # .npm-cache ディレクトリ削除
        if [ -d "${NEXT_APP_DIR}/.npm-cache" ]; then
            log "削除中: ${NEXT_APP_DIR}/.npm-cache"
            rm -rf "${NEXT_APP_DIR}/.npm-cache"
        fi
        
        # .tmp ディレクトリ削除
        if [ -d "${NEXT_APP_DIR}/.tmp" ]; then
            log "削除中: ${NEXT_APP_DIR}/.tmp"
            rm -rf "${NEXT_APP_DIR}/.tmp"
        fi
        
        log "Next.jsビルドデータのクリーンアップ完了"
    else
        log "Next.jsアプリディレクトリが見つかりません: ${NEXT_APP_DIR}"
    fi
}

# 古いログファイルのクリーンアップ（30日以上前）
cleanup_old_logs() {
    log "古いログファイルをクリーンアップ中..."
    
    # 30日以上前のログファイルを削除
    find "${SCRIPT_DIR}/logs" -name "*.log" -type f -mtime +30 -delete 2>&1 | tee -a "${LOG_FILE}"
    
    log "古いログファイルのクリーンアップ完了"
}

# メイン処理
main() {
    log "=========================================="
    log "ディスククリーンアップスクリプト開始"
    log "=========================================="
    
    # 現在の空き容量を取得
    AVAILABLE_MB=$(get_available_space_mb)
    log "現在のディスク空き容量: ${AVAILABLE_MB} MB"
    
    # 閾値チェック
    if [ "${AVAILABLE_MB}" -lt "${THRESHOLD_MB}" ]; then
        log "警告: ディスク空き容量が ${THRESHOLD_MB} MB を下回りました"
        log "クリーンアップを実行します..."
        
        # クリーンアップ前の容量
        BEFORE_MB=$(get_available_space_mb)
        
        # クリーンアップ実行
        cleanup_docker_build_cache
        cleanup_nextjs_build
        cleanup_old_logs
        
        # クリーンアップ後の容量
        AFTER_MB=$(get_available_space_mb)
        FREED_MB=$((AFTER_MB - BEFORE_MB))
        
        log "クリーンアップ完了"
        log "解放された容量: ${FREED_MB} MB"
        log "現在の空き容量: ${AFTER_MB} MB"
        
        # まだ容量が少ない場合は警告
        if [ "${AFTER_MB}" -lt "${THRESHOLD_MB}" ]; then
            log "警告: クリーンアップ後も空き容量が不足しています (${AFTER_MB} MB)"
            log "手動での対応が必要です"
        fi
    else
        log "ディスク空き容量は十分です (${AVAILABLE_MB} MB > ${THRESHOLD_MB} MB)"
        log "クリーンアップは不要です"
    fi
    
    log "=========================================="
    log "ディスククリーンアップスクリプト終了"
    log "=========================================="
    echo ""
}

# スクリプト実行
main
