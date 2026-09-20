# ✅ Laravel ToDo

> Laravel 12 / Blade / Tailwind CSS v4 で作ったタスク管理 Web アプリケーション

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.3-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-42_passed-3FB950?style=flat-square)

---

## 📌 概要

ユーザーごとにタスクを登録・整理できる Web アプリです。
ステータス・優先度・期限でタスクを構造化し、検索／絞り込み／並び替えで
「いま着手すべきタスク」だけに集中できることを目指しました。

単なる CRUD で終わらせず、**認可・バリデーション・テスト・CI** といった
実務で求められる土台をひと通り備えています。

---

## ✨ 主な機能

| 機能 | 内容 |
|---|---|
| ユーザー認証 | 新規登録 / ログイン / ログアウト（ログイン試行回数の制限つき） |
| タスク管理 | 作成・一覧・詳細・編集・削除（削除はソフトデリート） |
| ステータス管理 | 未着手 / 進行中 / 完了 の 3 状態 |
| 優先度 | 高 / 中 / 低。優先度順の並び替えに対応 |
| 期限管理 | 期限切れ・期限間近を色分けして強調表示 |
| ワンクリック完了 | 一覧のチェックボックスから完了状態をトグル（完了日時も自動記録） |
| 検索・絞り込み | キーワード（タイトル・内容）／ステータス／優先度／期限切れのみ |
| 並び替え | 新しい順 / 古い順 / 期限が近い順 / 優先度が高い順 |
| ダッシュボード | ステータス別件数・期限切れ件数を集計表示（クリックでそのまま絞り込み） |
| ページネーション | 10 件ごと。絞り込み条件はページ送り後も保持 |
| ダークモード | OS 設定に追従しつつ、手動切り替えも可能（選択は端末に保存） |

---

## 🛠️ 技術スタック

| カテゴリ | 使用技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 12 |
| フロントエンド | Blade / Tailwind CSS v4 / Vite / Vanilla JS |
| データベース | MySQL 8.3（ローカル開発・テストは SQLite） |
| テスト | PHPUnit 11（Feature 38 件 / Unit 4 件） |
| 品質管理 | Laravel Pint / GitHub Actions |
| 実行環境 | Docker / Docker Compose |

---

## 🔧 設計上のこだわり

### 1. コントローラを薄く保つ
入力の検証は `FormRequest`、権限の判定は `Policy`、データの絞り込みは
モデルの**ローカルスコープ**に切り出し、コントローラには「流れ」だけを残しています。

```php
$tasks = $this->query()
    ->search($filters['keyword'])
    ->status($filters['status'])
    ->priority($filters['priority'])
    ->when($filters['overdue'], fn ($query) => $query->overdue())
    ->sorted($filters['sort'])
    ->paginate(10)
    ->withQueryString();
```

### 2. 状態を enum で型安全に扱う
ステータスと優先度は PHP の **backed enum**（`App\Enums\TaskStatus` / `TaskPriority`）で定義し、
モデルのキャスト・バリデーション（`Rule::enum()`）・画面のラベルと配色までを
1 か所に集約しました。選択肢が増えても修正箇所は enum だけで済みます。

### 3. 他人のデータに触れさせない
`TaskPolicy` で所有者チェックを行い、URL の ID を書き換えても他ユーザーのタスクには
アクセスできません。この挙動は専用のテストクラスで担保しています。

### 4. セキュリティの基本を押さえる
- パスワードはハッシュ化して保存（`hashed` キャスト）
- ログイン成功時に**セッション ID を再生成**（セッション固定攻撃対策）
- ログイン失敗 5 回で一時的にロック（総当たり対策）
- エラーメッセージでメールアドレスの存在有無を区別しない（ユーザー列挙対策）
- 全フォームに CSRF トークン、Blade のエスケープで XSS 対策

### 5. JavaScript が無くても壊れない
完了トグル・絞り込み・削除確認はいずれもフォーム送信で成立する作りにし、
JavaScript は「自動送信」「確認ダイアログ」「テーマ切り替え」といった
体験を上乗せする役割に留めています。

### 6. パフォーマンス
- 一覧の集計はステータス別の件数を **1 クエリ**（`GROUP BY`）で取得
- `user_id × status` / `user_id × due_date` の複合インデックスを付与
- 開発環境では `Model::shouldBeStrict()` を有効化し、N+1 を検出

---

## 🧪 テスト

```bash
php artisan test
```

```
Tests:  42 passed (100 assertions)
```

| テストクラス | 検証内容 |
|---|---|
| `Auth\RegistrationTest` | 登録処理、重複メール、パスワード確認・強度 |
| `Auth\AuthenticationTest` | ログイン／ログアウト、セッション再生成、回数制限 |
| `Task\TaskCrudTest` | CRUD、完了トグル、バリデーション、ソフトデリート |
| `Task\TaskAuthorizationTest` | 他ユーザーのタスクへの操作がすべて 403 になること |
| `Task\TaskFilterTest` | 検索・絞り込み・並び替え・ページング・集計 |
| `PageRenderTest` | 全画面の描画と 404 |
| `Unit\TaskTest` | 期限切れ・期限間近の判定ロジック |

---

## 🚀 セットアップ

### Docker で起動する

```bash
docker compose up --build
# → http://localhost:8000
```

初回起動時に `.env` の生成・アプリケーションキーの発行・マイグレーションが自動で実行されます。

### ローカルで起動する（SQLite）

```bash
cd laravel_app
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # デモデータを投入

php artisan serve
```

デモアカウント: `demo@example.com` / `password123`

---

## 📁 ディレクトリ構成（主要部分）

```
laravel_app/
├── app/
│   ├── Enums/                  # TaskStatus / TaskPriority
│   ├── Http/
│   │   ├── Controllers/        # Auth・Task（薄く保つ）
│   │   └── Requests/           # バリデーション + 認証ロジック
│   ├── Models/                 # Task（スコープ・判定ロジック）/ User
│   └── Policies/               # TaskPolicy（所有者チェック）
├── resources/
│   ├── css/app.css             # Tailwind のデザイントークン
│   └── views/
│       ├── components/         # Blade コンポーネント（バッジ・カード等）
│       ├── layouts/            # app / guest
│       ├── auth/ · tasks/      # 各画面
│       └── partials/
├── database/
│   ├── factories/ · seeders/   # デモデータ生成
│   └── migrations/
└── tests/
    ├── Feature/ · Unit/
```

---

## 📸 スクリーンショット

> UI を全面的に刷新したため、スクリーンショットは撮り直しが必要です。
> `imags/` 配下の画像は旧デザインのものです。

---

## 🔭 今後の展望

- タグ／カテゴリによる分類（多対多リレーション）
- ドラッグ＆ドロップによる並び替え
- 期限が近いタスクのメール通知（Queue + Notification）
- REST API 化（Laravel Sanctum）
