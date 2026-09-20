# ✅ Laravel ToDo

> Laravel 12 / Blade / Tailwind CSS v4 で作ったタスク管理 Web アプリケーション

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.3-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-88_passed-3FB950?style=flat-square)

---

## 📌 概要

ユーザーごとにタスクを登録・整理できる Web アプリです。
ステータス・優先度・期限・タグでタスクを構造化し、
**一覧 / カンバンボード / 分析ダッシュボード**という 3 つの見え方を用意して、
「いま着手すべきタスク」だけに集中できることを目指しました。

本文はリッチテキストエディタで書けるので、手順やメモをそのまま残せます。
単なる CRUD で終わらせず、**認可・バリデーション・サニタイズ・テスト・CI** といった
実務で求められる土台をひと通り備えています。

---

## ✨ 主な機能

| 機能 | 内容 |
|---|---|
| ユーザー認証 | 新規登録 / ログイン / ログアウト（ログイン試行回数の制限つき） |
| タスク管理 | 作成・一覧・詳細・編集・削除（削除はソフトデリート） |
| **リッチテキスト** | 見出し・太字・リスト・**チェックリスト**・引用・コード・リンクを本文に記述（Tiptap） |
| **カンバンボード** | 未着手 / 進行中 / 完了の 3 レーンをドラッグ＆ドロップで移動・並び替え |
| **サブタスク** | タスク内にチェックリストを持ち、達成率をプログレスバーで表示 |
| **タグ管理** | 色付きタグの作成・編集・削除（多対多）。タグでの絞り込みにも対応 |
| **分析ダッシュボード** | 日別の完了数推移・完了率・優先度別の内訳・連続達成日数・期限が近いタスク |
| ステータス管理 | 未着手 / 進行中 / 完了 の 3 状態 |
| 優先度 | 高 / 中 / 低。優先度順の並び替えに対応 |
| 期限管理 | 期限切れ・期限間近を色分けして強調表示 |
| ワンクリック完了 | 一覧のチェックボックスから完了状態をトグル（完了日時も自動記録） |
| 検索・絞り込み | キーワード（タイトル・本文）／ステータス／優先度／タグ／期限切れのみ |
| 並び替え | 新しい順 / 古い順 / 期限が近い順 / 優先度が高い順 |
| ページネーション | 10 件ごと。絞り込み条件はページ送り後も保持 |
| ダークモード | OS 設定に追従しつつ、手動切り替えも可能（選択は端末に保存） |

---

## 🛠️ 技術スタック

| カテゴリ | 使用技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 12 |
| フロントエンド | Blade / Tailwind CSS v4 / Vite / Vanilla JS |
| エディタ | Tiptap v3（ProseMirror）+ HTMLPurifier によるサーバー側サニタイズ |
| ドラッグ&ドロップ | SortableJS |
| データベース | MySQL 8.3（ローカル開発・テストは SQLite） |
| テスト | PHPUnit 11（Feature 75 件 / Unit 13 件） |
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

### 5. リッチテキストを安全に扱う
リッチテキストは「HTML をそのまま保存して表示する」機能なので、XSS の入口になり得ます。
そこで **保存の直前に必ずサニタイズされる**よう、モデルのミューテータに処理を寄せました。
コントローラ側の書き忘れでは素通りしません。

```php
protected function content(): Attribute
{
    return Attribute::set(function (?string $value) {
        $html = RichText::sanitize($value);          // 許可タグ以外を除去

        return [
            'content' => $html,
            'content_text' => RichText::toPlainText($html),  // 検索用の平文も同期
        ];
    });
}
```

- 許可するのは見出し・リスト・チェックリストなど**エディタが生成しうるタグだけ**
- `href` は `http(s)` と `mailto` のみ許可し、`javascript:` を弾く
- `<script>` やイベント属性（`onclick` など）は除去
- HTML のままだと検索が `<p>` などのタグに誤ヒットするため、**平文カラムを別に持って検索対象にする**

この挙動は `RichTextTest` で XSS のパターンごとに検証しています。

### 6. JavaScript が無くても壊れない
完了トグル・サブタスク・絞り込み・削除確認はいずれもフォーム送信で成立する作りです。
リッチエディタも `<noscript>` で通常のテキストエリアにフォールバックします。
（カンバンのドラッグ＆ドロップのみ JS 必須で、その旨を画面に明記しています）

### 7. パフォーマンス
- 一覧の集計はステータス別の件数を **1 クエリ**（`GROUP BY`）で取得
- サブタスクの進捗は `withCount` で集計し、一覧での N+1 を回避
- `user_id × status` / `user_id × due_date` の複合インデックスを付与
- 開発環境では `Model::shouldBeStrict()` を有効化し、N+1 を検出
- 重い依存（Tiptap / SortableJS）は**動的 import** で必要なページだけ読み込む
  （初期バンドル 53KB / エディタ 381KB / ボード 38KB に分割）

### 8. グラフは配色まで根拠を持たせる
ダッシュボードのグラフは外部ライブラリを使わず、サーバー側で組み立てた **インライン SVG** です。

- 日別の完了数は**単一系列**なので 1 色（凡例は不要、タイトルが系列名を兼ねる）
- 優先度は「低 < 中 < 高」の**順序尺度**なので、同一色相の明→暗ランプを使用
- 配色はライト／ダークそれぞれの背景色に対して、明度・彩度・コントラストを検証して決定
- 色だけに頼らないよう、内訳は件数と割合を文字でも併記

---

## 🧪 テスト

```bash
php artisan test
```

```
Tests:  88 passed (194 assertions)
```

| テストクラス | 検証内容 |
|---|---|
| `Auth\RegistrationTest` | 登録処理、重複メール、パスワード確認・強度 |
| `Auth\AuthenticationTest` | ログイン／ログアウト、セッション再生成、回数制限 |
| `Task\TaskCrudTest` | CRUD、完了トグル、バリデーション、ソフトデリート |
| `Task\TaskAuthorizationTest` | 他ユーザーのタスクへの操作がすべて 403 になること |
| `Task\TaskFilterTest` | 検索・絞り込み・並び替え・ページング・集計 |
| `Task\TaskContentTest` | リッチテキストの保存・サニタイズ・平文カラムの同期 |
| `Task\SubtaskTest` | サブタスクの追加・トグル・削除・進捗率・所有者チェック |
| `Tag\TagTest` | タグの CRUD、ユーザー単位の一意制約、付け外し、絞り込み |
| `BoardTest` | レーン分け、ドラッグ＆ドロップ後の状態・並び順の保存、他人のタスクの保護 |
| `DashboardTest` | 完了率・推移・内訳・連続達成日数の集計（0 件やデータ欠損も含む） |
| `PageRenderTest` | 全画面の描画（データ 0 件のケースを含む）と 404 |
| `Unit\TaskTest` | 期限切れ・期限間近の判定ロジック |
| `Unit\RichTextTest` | XSS 除去・チェックリスト構造の保持・平文変換 |

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
php artisan migrate --seed   # デモデータ（タスク100件・タグ6種）を投入

php artisan serve
```

デモアカウント: `demo@example.com` / `password123`

シーダーはタスク 100 件を、ステータス・優先度・期限・タグ・サブタスクを散らして作成します。
完了日は過去 2 週間に分散させているため、ダッシュボードの推移グラフやページネーションも
投入直後から確認できます。

---

## 📁 ディレクトリ構成（主要部分）

```
laravel_app/
├── app/
│   ├── Enums/                  # TaskStatus / TaskPriority / TagColor
│   ├── Http/
│   │   ├── Controllers/        # Task・Board・Dashboard・Tag（薄く保つ）
│   │   └── Requests/           # バリデーション + 認証ロジック
│   ├── Models/                 # Task / Subtask / Tag / User
│   ├── Policies/               # 所有者チェック
│   └── Support/RichText.php    # HTML のサニタイズと平文変換
├── resources/
│   ├── css/app.css             # デザイントークン・エディタ・グラフの配色
│   ├── js/features/            # editor.js（Tiptap）/ board.js（D&D）
│   └── views/
│       ├── components/         # Blade コンポーネント（エディタ・バッジ等）
│       ├── layouts/            # app / guest
│       ├── auth/ · tasks/      # 各画面
│       ├── board/ · dashboard/ · tags/
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

- 期限が近いタスクのメール通知（Queue + Notification）
- 繰り返しタスク（毎週・毎月）
- REST API 化（Laravel Sanctum）とモバイル対応
- 全文検索エンジンの導入（Laravel Scout）
