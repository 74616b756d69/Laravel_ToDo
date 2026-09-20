#!/usr/bin/env bash
set -e

# ---------------------------------------------------------------------------
# .env を用意する
#
# `php artisan serve` は子プロセスへ一部の環境変数しか引き継がないため、
# compose で渡した DB_* などは Web リクエスト側から見えない。
# そのため、コンテナの環境変数を .env に書き戻して両者の設定を一致させる。
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    cp .env.example .env
fi

upsert_env() {
    local key="$1"
    local value="$2"

    if grep -qE "^#?\s*${key}=" .env; then
        # 値に & や / が含まれても壊れないよう、区切り文字に | を使う
        sed -i -E "s|^#?\s*${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

for key in APP_NAME APP_ENV APP_DEBUG APP_URL APP_LOCALE \
           DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION MAIL_MAILER \
           DEMO_LOGIN_ENABLED DEMO_LOGIN_EMAIL DEMO_LOGIN_PASSWORD; do
    if [ -n "${!key+x}" ]; then
        upsert_env "$key" "${!key}"
    fi
done

# APP_KEY が未設定なら発行する（初回起動時）
if ! grep -qE '^APP_KEY=.+' .env; then
    php artisan key:generate --force
fi

# 設定キャッシュが残っていると古い接続先を掴むので、起動のたびに捨てる
php artisan config:clear > /dev/null

# ---------------------------------------------------------------------------
# データベースの起動を待ってからマイグレーションを流す
# ---------------------------------------------------------------------------
attempt=0
until php artisan db:monitor > /dev/null 2>&1; do
    attempt=$((attempt + 1))

    if [ "$attempt" -ge 30 ]; then
        echo "データベースに接続できませんでした。" >&2
        exit 1
    fi

    echo "データベースの起動を待機しています... (${attempt}/30)"
    sleep 2
done

# ---------------------------------------------------------------------------
# マイグレーションを流す
#
# 課題（Issue）化とワークフロー化は、スキーマの変更だけでは完結しない。
# 途中に移行コマンドを 2 回挟む必要があり、順序はこうなる:
#
#   M1 → issues:migrate-from-tasks → M3・M4・M5 → workflows:install → M6・M7 …
#
# 中身が埋まらないまま NOT NULL を張ろうとすると、M3 と M6 がわざと例外で
# 止まる（半端な状態で制約を張ると、そこで初めてデータが壊れるため）。
#
# まっさらなデータベースでは最初の migrate が最後まで通り、
# 移行コマンドは「対象なし」で何もしない。どちらのコマンドも冪等なので、
# 再起動のたびに実行しても結果は変わらない。
# ---------------------------------------------------------------------------

# ガードで止まるのは想定どおりなので、ここでは失敗を通す。
# 本当に直らない問題なら、最後の migrate が改めて落ちる。
migrate_until_guard() {
    php artisan migrate --force || true
}

migrate_until_guard
php artisan issues:migrate-from-tasks
migrate_until_guard
php artisan workflows:install
php artisan migrate --force

# SEED_DATABASE=true かつ、まだユーザーが 1 人もいないときだけデモデータを投入する
# （再起動のたびに流すとメールアドレスの一意制約で落ちるため）
if [ "${SEED_DATABASE:-false}" = "true" ]; then
    if [ "$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tr -dc '0-9')" = "0" ]; then
        php artisan db:seed --force
    else
        echo "既存のデータがあるため、シードはスキップしました。"
    fi
fi

exec "$@"
