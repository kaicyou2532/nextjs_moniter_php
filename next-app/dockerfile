FROM node:18-alpine

# アプリケーションを配置するディレクトリを作成
WORKDIR /app

# パッケージ定義ファイルをコピー
COPY package*.json ./

# 依存関係をインストール
RUN npm install

# 残りのアプリケーションのコードを全てコピー
COPY . .

# アプリが使用するポート（Next.jsのデフォルトは3000）
EXPOSE 3000

# アプリを開発モードで起動
CMD ["npm", "run", "dev"]