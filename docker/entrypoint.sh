#!/usr/bin/env bash
set -e

# .env が無ければ雛形から作る（初回起動時）
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# DB の起動を待ってからマイグレーションを流す
until php artisan db:monitor > /dev/null 2>&1; do
    echo "データベースの起動を待機しています..."
    sleep 2
done

php artisan migrate --force

exec "$@"
