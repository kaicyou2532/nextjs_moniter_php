# AIMcommonsサイト 管理ツール / AIMcommons website Management Tool

## 概要 / Overview
Docker を使わずに、PHP ベースの Web インターフェースから Next.js アプリケーションを  
- ビルド  
- 起動  
- 停止  
- 再起動  
- 状態確認  

できるシンプルなツールです。  
This is a simple PHP-based web tool to  
- build  
- start  
- stop  
- restart  
- check status  

of a Next.js application without using Docker.

---

## 目次 / Table of Contents
1. [前提条件 / Prerequisites](#前提条件--prerequisites)  
2. [ディレクトリ構成 / Directory Structure](#ディレクトリ構成--directory-structure)  
3. [インストール / Installation](#インストール--installation)  
4. [設定 / Configuration](#設定--configuration)  
5. [使い方 / Usage](#使い方--usage)  
6. [パーミッション / Permissions](#パーミッション--permissions)  
7. [macOS での検証 / macOS Testing](#macos-での検証--macos-testing)  
8. [ライセンス / License](#ライセンス--license)

---

## 前提条件 / Prerequisites
- PHP 7.4 以上（CLI と `exec` / `shell_exec` が有効）  
- Node.js と npm  
- Next.js プロジェクト（例：`next-app/` 配下）  
- Web サーバー（Apache/Nginx + PHP-FPM、または組み込みサーバー）  

---

## ディレクトリ構成 / Directory Structure

```
my-next-builder/
├── public/
│   ├── index.php       ← フロントエンド UI  
│   └── api.php         ← Ajax エンドポイント  
├── next-app/           ← Next.js プロジェクト配置先  
├── logs/
│   └── nextjs.log      ← 起動時のログ出力先  
├── pids/
│   └── nextjs.pid      ← プロセス ID 保存先  
└── README.md           ← このファイル  
```

- `public/` を Web ドキュメントルートに設定してください。  
- `logs/` と `pids/` は PHP プロセスから書き込み可能にします。

---

## インストール / Installation
1. リポジトリをクローン  
   ```bash
   git clone https://github.com/yourname/my-next-builder.git
   cd my-next-builder
   ```
2. Next.js 依存関係をインストール  
   ```bash
   cd next-app
   npm install
   ```
3. `public/` を Web サーバーのドキュメントルートに設定  
   - **Apache**: `DocumentRoot /path/to/my-next-builder/public`  
   - **Nginx + PHP-FPM**: `root /path/to/my-next-builder/public;`

---

## 設定 / Configuration
- `BASE_DIR`、`NEXT_DIR`、`LOG_FILE`、`PID_FILE` のパスは `public/api.php` 内で必要に応じて調整してください。  
- `npm run start` が起動するコマンドおよびポート（デフォルト: 3000）も Next.js 側で設定してください。

---

## 使い方 / Usage
1. ブラウザで管理画面にアクセス  
   ```  
   http://your-server/
   ```  
2. ボタンをクリックして操作  
   - **ビルド / Build**  
   - **起動 / Start**  
   - **停止 / Stop**  
   - **再起動 / Restart**  
   - **状態確認 / Status**  

各操作は `public/api.php` 経由でシェルコマンドを実行し、結果を画面に表示します。

---

## パーミッション / Permissions
```bash
cd my-next-builder
chmod -R 775 logs pids
chown -R www-data:www-data logs pids   # Ubuntu/Apache の場合
```
`logs/` と `pids/` に PHP プロセス (例: `www-data`) が書き込めるようにします。

---

## macOS での検証 / macOS Testing
組み込み PHP サーバーで動作確認可能です。
```bash
cd path/to/my-next-builder/public
php -S localhost:8000
```
ブラウザで `http://localhost:8000` にアクセスして、同様に操作してください。

---

## ライセンス / License
MIT License  
© 2025 中村喜一
