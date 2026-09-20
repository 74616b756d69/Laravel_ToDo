# ✅ Laravel ToDo

> Laravel 12 / Blade / Tailwind CSS v4 で作ったタスク管理 Web アプリケーション

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.3-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-151_passed-3FB950?style=flat-square)

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
| **クイック追加** | 1 行書くだけでタスク化。期限・タグ・優先度を文章から自動で読み取る |
| **レーンから追加** | ボードの各レーン下部から、そのステータスでタスクを直接追加 |
| ユーザー認証 | 新規登録 / ログイン / ログアウト（ログイン試行回数の制限つき） |
| タスク管理 | 作成・一覧・詳細・編集・削除（削除はソフトデリート） |
| **リッチテキスト** | 見出し・太字・リスト・**チェックリスト**・引用・コード・リンクを本文に記述（Tiptap） |
| **カンバンボード** | 未着手 / 進行中 / 完了の 3 レーンをドラッグ＆ドロップで移動・並び替え（レーン内スクロール） |
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
| **デモアカウント** | ログイン画面から 1 クリックで、タスク 100 件入りのアカウントを体験可能 |

---

## 🛠️ 技術スタック

| カテゴリ | 使用技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 12 |
| フロントエンド | Blade / Tailwind CSS v4 / Vite / Vanilla JS |
| エディタ | Tiptap v3（ProseMirror）+ HTMLPurifier によるサーバー側サニタイズ |
| ドラッグ&ドロップ | SortableJS |
| データベース | MySQL 8.3（ローカル開発・テストは SQLite） |
| テスト | PHPUnit 11（Feature 106 件 / Unit 45 件） |
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

### 2. 「書き留めるまで」を最短にする
タスク管理ツールを使わなくなる一番の理由は、**1 件登録するのが面倒**だからだと考えました。
そこで一覧の先頭に 1 行の入力欄を置き、文章から属性を読み取るようにしています。

```
明日 請求書を送る #仕事 !高
  ↓
タイトル: 請求書を送る / 期限: 2026-09-21 / タグ: 仕事 / 優先度: 高
```

| 記法 | 例 |
|---|---|
| 日付 | `今日` `明日` `明後日` `来週` `来月` `3日後` `2週間後` `月曜` `来週金曜` `今週末` `今月末` `9/25` `2027-01-09` |
| タグ | `#仕事`（登録済みのタグ名と一致したものだけ採用） |
| 優先度 | `!高` `!中` `!低` / `!high` `!medium` `!low` |

設計で意識したこと。

- **入力が消えないこと** ― 解釈できなかった部分はすべてタイトルに残します。
  記法として誤認識されて内容が消える、という事故を防ぐためです
- **解釈結果を必ず見せること** ―
  「『請求書を送る』を追加しました（期限 9/21(月) / 優先度高 / タグ 仕事）」のように、
  何をどう読み取ったかを返します。意図と違えばその場で気づけます
- **日本語入力への対応** ― 全角の `＃` `！` `３日後` も半角に正規化してから解析します
- **他人のタグは付かないこと** ― 照合対象はログインユーザーのタグだけです
- **JavaScript に依存しないこと** ― 解析はサーバー側。通常のフォーム送信で完結します

解析ロジックは `QuickAddParser` に切り出し、表記ごとに単体テストを書いています（32 件）。
同じ入力欄をボードの各レーン下部にも置き、そこから追加したタスクは
**そのレーンのステータスで、末尾に**入ります（追加後はそのカードの位置まで自動で送られます）。

### 3. 状態を enum で型安全に扱う
ステータスと優先度は PHP の **backed enum**（`App\Enums\TaskStatus` / `TaskPriority`）で定義し、
モデルのキャスト・バリデーション（`Rule::enum()`）・画面のラベルと配色までを
1 か所に集約しました。選択肢が増えても修正箇所は enum だけで済みます。

### 4. 他人のデータに触れさせない
`TaskPolicy` で所有者チェックを行い、URL の ID を書き換えても他ユーザーのタスクには
アクセスできません。この挙動は専用のテストクラスで担保しています。

### 5. セキュリティの基本を押さえる
- パスワードはハッシュ化して保存（`hashed` キャスト）
- ログイン成功時に**セッション ID を再生成**（セッション固定攻撃対策）
- ログイン失敗 5 回で一時的にロック（総当たり対策）
- エラーメッセージでメールアドレスの存在有無を区別しない（ユーザー列挙対策）
- 全フォームに CSRF トークン、Blade のエスケープで XSS 対策

### 6. リッチテキストを安全に扱う
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

### 7. JavaScript が無くても壊れない
完了トグル・サブタスク・絞り込み・削除確認はいずれもフォーム送信で成立する作りです。
リッチエディタも `<noscript>` で通常のテキストエリアにフォールバックします。
（カンバンのドラッグ＆ドロップのみ JS 必須で、その旨を画面に明記しています）

### 8. パフォーマンス
- 一覧の集計はステータス別の件数を **1 クエリ**（`GROUP BY`）で取得
- サブタスクの進捗は `withCount` で集計し、一覧での N+1 を回避
- `user_id × status` / `user_id × due_date` の複合インデックスを付与
- 開発環境では `Model::shouldBeStrict()` を有効化し、N+1 を検出
- 重い依存（Tiptap / SortableJS）は**動的 import** で必要なページだけ読み込む
  （初期バンドル 53KB / エディタ 381KB / ボード 38KB に分割）

### 9. グラフは配色まで根拠を持たせる
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
Tests:  151 passed (356 assertions)
```

| テストクラス | 検証内容 |
|---|---|
| `Auth\RegistrationTest` | 登録処理、重複メール、パスワード確認・強度 |
| `Auth\AuthenticationTest` | ログイン／ログアウト、セッション再生成、回数制限 |
| `Auth\DemoLoginTest` | デモログインの動作、セッション再生成、設定による無効化 |
| `Task\TaskCrudTest` | CRUD、完了トグル、バリデーション、ソフトデリート |
| `Task\TaskAuthorizationTest` | 他ユーザーのタスクへの操作がすべて 403 になること |
| `Task\TaskFilterTest` | 検索・絞り込み・並び替え・ページング・集計 |
| `Task\QuickAddTest` | クイック追加の登録・解釈結果の通知・他人のタグの除外・入力検証 |
| `Task\TaskContentTest` | リッチテキストの保存・サニタイズ・平文カラムの同期 |
| `Task\SubtaskTest` | サブタスクの追加・トグル・削除・進捗率・所有者チェック |
| `Tag\TagTest` | タグの CRUD、ユーザー単位の一意制約、付け外し、絞り込み |
| `BoardTest` | レーン分け、ドラッグ＆ドロップ後の状態・並び順の保存、他人のタスクの保護 |
| `DashboardTest` | 完了率・推移・内訳・連続達成日数の集計（0 件やデータ欠損も含む） |
| `PageRenderTest` | 全画面の描画（データ 0 件のケースを含む）と 404 |
| `Unit\TaskTest` | 期限切れ・期限間近の判定ロジック |
| `Unit\QuickAddParserTest` | 日付・タグ・優先度の各表記、全角入力、誤認識の防止 |
| `Unit\RichTextTest` | XSS 除去・チェックリスト構造の保持・平文変換 |
| `DemoSeederTest` | デモデータの件数・内訳・各指標が意味のある値になること |

---

## 🚀 セットアップ

### Docker で起動する

```bash
docker compose up --build
# → http://localhost:8000
```

これだけで MySQL ごと起動し、そのまま新規登録してタスクを作れます。
初回起動時に以下が自動で実行されます。

1. `.env` の生成と、compose で指定した設定（DB 接続先など）の反映
2. アプリケーションキーの発行
3. MySQL の起動待ち → マイグレーション
4. デモデータの投入（タスク 100 件。`SEED_DATABASE: "false"` で無効化）

停止と初期化:

```bash
docker compose down      # 停止（データは残る）
docker compose down -v   # DB のデータごと削除
```

<details>
<summary>詰まりどころ: <code>artisan serve</code> は環境変数を子プロセスに渡さない</summary>

`php artisan serve` は、起動する PHP ビルトインサーバーへ**一部の環境変数しか引き継ぎません**。
そのため compose の `environment:` に `DB_CONNECTION: mysql` を書いても、
CLI（`php artisan migrate`）からは見えるのに、**Web リクエストからは見えない**という状態になります。
結果、Web 側だけが `.env` の既定値（SQLite）にフォールバックし、

```
Database file at path [.../database.sqlite] does not exist.
```

というエラーになります。

本リポジトリでは、エントリポイントでコンテナの環境変数を `.env` に書き戻し、
CLI と Web の設定を一致させることで解決しています（`docker/entrypoint.sh`）。
</details>

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

---

## 🧑‍💻 デモアカウント

**ログイン画面の「デモアカウントでログイン」ボタンから、登録せずにそのまま体験できます。**
手入力する場合は `demo@example.com` / `password123` です。

`DemoUserSeeder` は「ログインした瞬間にどの画面も成立していること」を狙って、
ランダム任せにせず件数を設計しています。

| 作られるもの | 内容 |
|---|---|
| タスク | 100 件（未着手・進行中・完了をバランスよく配分） |
| 完了履歴 | 過去 14 日に分散。直近 5 日は必ず 1 件以上入れて**連続達成日数**が途切れないようにする |
| 優先度 | 未完了タスクを 高:中:低 ≒ 2:3:3 で配分し、内訳グラフが 1 色に偏らないようにする |
| 期限 | 期限切れ・期限間近・余裕あり・未設定を混ぜ、色分けの挙動を確認できるようにする |
| タグ | 6 種（仕事 / プライベート / 学習 / 至急 / 事務手続き / あとで読む） |
| サブタスク | 約 4 分の 1 のタスクに 2〜5 件（進捗バーの確認用） |
| 本文 | 約 8 割に見出し・リスト・チェックリスト入りのリッチテキスト |
| 別ユーザー | `other@example.com` にもタスクを作成し、データが分離されていることを確認できる |

再実行しても件数が積み増されないよう、シーダーは冪等にしています。

公開時にデモの入口を閉じる場合は、環境変数で無効化できます。

```dotenv
DEMO_LOGIN_ENABLED=false
```

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
│   └── Support/
│       ├── QuickAddParser.php  # 1 行入力の解析
│       └── RichText.php        # HTML のサニタイズと平文変換
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
│   ├── factories/
│   ├── seeders/                # DemoUserSeeder（分析データ入りのデモアカウント）
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
- クイック追加の入力中プレビュー（打ちながら解釈結果を表示）
- REST API 化（Laravel Sanctum）とモバイル対応
- 全文検索エンジンの導入（Laravel Scout）
