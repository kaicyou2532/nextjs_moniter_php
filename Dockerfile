# PHP管理ツール用Dockerfile
FROM php:8.3-apache

# 必要なパッケージをインストール
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    nginx \
    procps \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Node.js 20.x をインストール
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 必要なPHP拡張をインストール
RUN docker-php-ext-install pcntl posix

# Apacheの設定
RUN a2enmod rewrite
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# 作業ディレクトリの設定
WORKDIR /var/www/html

# アプリケーションファイルをコピー
COPY . .

# 必要なディレクトリを作成
RUN mkdir -p logs pids \
    && chmod 755 logs pids \
    && chown -R www-data:www-data /var/www/html

# nginxの簡素化された設定
COPY nginx.conf /etc/nginx/nginx.conf

# Supervisorの設定
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 作業ディレクトリの設定
WORKDIR /var/www/html

# アプリケーションファイルをコピー
COPY . .

# 必要なディレクトリを作成
RUN mkdir -p logs pids \
    && chmod 755 logs pids \
    && chown -R www-data:www-data /var/www/html

# ポート公開
EXPOSE 80 3000

# 環境変数の設定
ENV GITURL=""

# Supervisorで複数サービスを起動
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
