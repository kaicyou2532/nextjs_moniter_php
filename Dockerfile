# PHP管理ツール用Dockerfile
FROM php:8.3-apache

# 環境変数設定
ENV DEBIAN_FRONTEND=noninteractive

# 基本パッケージのインストール
RUN apt-get update && \
    apt-get install -y \
        curl \
        git \
        unzip \
        nginx \
        procps \
        supervisor \
        wget \
    && rm -rf /var/lib/apt/lists/*

# Node.js のインストール
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# PHP拡張のインストール
RUN docker-php-ext-install pcntl posix

# Git設定（安全なディレクトリとして追加）
RUN git config --global --add safe.directory /var/www/html/next-app && \
    git config --global --add safe.directory '*'

# Apache設定
RUN a2enmod rewrite
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# ログディレクトリ作成
RUN mkdir -p /var/log/supervisor

# 設定ファイルをコピー
COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 作業ディレクトリ設定
WORKDIR /var/www/html

# プロジェクトファイルをコピー
COPY . /var/www/html/

# ディレクトリの作成と権限設定
RUN mkdir -p logs pids next-app && \
    chmod -R 755 logs pids next-app && \
    chown -R www-data:www-data /var/www/html

# ポートを公開
EXPOSE 80 3000

# Supervisorで起動
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
