# PHP管理ツール用Dockerfile
FROM php:8.3-apache

# パッケージインストール
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    nginx \
    procps \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Node.js 20.x インストール
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# PHP拡張インストール
RUN docker-php-ext-install pcntl posix

# Apache mod_rewrite有効化
RUN a2enmod rewrite

# Apache設定
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# nginx設定
COPY nginx.conf /etc/nginx/nginx.conf

# supervisor設定
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 作業ディレクトリ設定
WORKDIR /var/www/html

# ファイルをコピー
COPY . .

# ディレクトリ作成と権限設定
RUN mkdir -p logs pids \
    && chmod 755 logs pids \
    && chown -R www-data:www-data /var/www/html \
    && chmod +x /var/www/html/public/*.php

# ポート公開
EXPOSE 80 3000

# 環境変数
ENV GITURL=""
ENV DEBIAN_FRONTEND=noninteractive

# デーモンモードでSupervisorを起動
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
