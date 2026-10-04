# ✅ Laravel ToDo

> Laravel 12 / Blade / Tailwind CSS v4 で作った、チーム向けの課題管理 Web アプリケーション（最小限の Jira クローン）

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.3-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-516_passed-3FB950?style=flat-square)

---

## 📌 概要

個人向けの ToDo アプリとして作り始め、**複数人・複数プロジェクトで使える課題管理ツール**へ作り替えました。
プロジェクトごとに課題（Issue）を `PROJ-123` のようなキーで管理し、
**バックログ → スプリント → カンバンボード → ダッシュボード**の流れで、チームの作業を回せます。

ステータスは固定ではなく、プロジェクトごとに**ワークフロー（ステータスと許可する遷移）**を設定できます。
課題の詳細画面は項目ごとのインライン編集で、編集フォームへ移動せずにその場で直せます。

単なる CRUD で終わらせず、**ロールベースの認可・採番の競合対策・既存データの移行・サニタイズ・テスト・CI**
といった実務で求められる土台をひと通り備えています。

---

## ✨ 主な機能

| 機能 | 内容 |
|---|---|
| **プロジェクト** | 作成・編集・削除、メンバー招待。ヘッダーから現在のプロジェクトを切り替え |
| **ロール** | 管理者（admin）/ メンバー（member）/ 閲覧者（viewer）。操作ごとに権限を判定 |
| **課題キー** | `PROJ-123` 形式でプロジェクト内連番。URL も `/browse/PROJ-123`（旧 `/tasks/{id}` は自動転送） |
| **課題タイプ** | エピック / ストーリー / タスク / バグ / サブタスク。親子関係（1 段）を持てる |
| **ワークフロー** | ステータスの追加・名前変更・並び替え・削除と、許可する遷移の設定（管理者のみ） |
| **スプリント** | 作成・開始・完了。完了時に未完了の課題をバックログか次のスプリントへ送る |
| **バックログ** | スプリントと未割り当ての課題をドラッグ＆ドロップで振り分け |
| **カンバンボード** | ワークフローのステータスをレーンにして、ドラッグ＆ドロップで遷移・並び替え |
| **インライン編集** | 詳細画面で、タイトル・本文・ステータス・担当者・タイプ・優先度・期限・ストーリーポイント・タグを 1 項目ずつ更新 |
| **通知とウォッチ** | 担当になった・ウォッチ中の課題が動いた・コメントが付いたときにアプリ内で通知（担当はメールでも）。起票者・担当者・コメントした人は自動でウォッチ |
| **コメントと履歴** | リッチテキストのコメント（編集・削除）と、ステータス・担当者などの変更履歴をタイムライン表示 |
| **課題リンク** | 「関連する」「ブロックする」「重複する」で課題同士を関連づけ |
| **クイック追加** | 1 行書くだけで課題化。期限・タグ・優先度を文章から自動で読み取る |
| **検索** | ヘッダーの検索窓にキーを打つと詳細へ直行、それ以外はキーワード検索 |
| **絞り込み・並び替え** | キーワード（タイトル・本文）／ステータス／優先度／タグ／期限切れのみ。10 件ごとのページネーション |
| **リッチテキスト** | 見出し・太字・リスト・**チェックリスト**・引用・コード・リンクを本文に記述（Tiptap） |
| **タグ管理** | 色付きタグの作成・編集・削除（多対多） |
| **分析ダッシュボード** | 進行中スプリントのバーンダウン・日別の完了数推移・完了率・優先度別の内訳・連続達成日数・期限が近い課題 |
| ユーザー認証 | 新規登録 / ログイン / ログアウト（ログイン試行回数の制限つき） |
| ダークモード | OS 設定に追従しつつ、手動切り替えも可能（選択は端末に保存） |
| **デモアカウント** | ログイン画面から 1 クリックで、課題 100 件・スプリント入りのアカウントを体験可能 |

---

## 🛠️ 技術スタック

| カテゴリ | 使用技術 |
|---|---|
| バックエンド | PHP 8.4 / Laravel 12 |
| フロントエンド | Blade / Tailwind CSS v4 / Vite / Vanilla JS |
| エディタ | Tiptap v3（ProseMirror）+ HTMLPurifier によるサーバー側サニタイズ |
| ドラッグ&ドロップ | SortableJS |
| データベース | MySQL 8.3（ローカル開発・テストは SQLite） |
| テスト | PHPUnit 11（Feature 450 件 / Unit 66 件） |
| 品質管理 | Laravel Pint / GitHub Actions |
| 非同期処理 | Laravel Queue（database ドライバ）。通知メールはキュー経由で送信 |
| 実行環境 | Docker / Docker Compose |

---

## 🗂️ データモデル

```
Organization ─┬─ Project ─┬─ ProjectMember（User × ロール）
              │           ├─ Status ── Transition（許可する遷移）
              │           ├─ Sprint
              │           └─ Issue ─┬─ 親子（parent_id）
              │                     ├─ IssueLink（関連 / ブロック / 重複）
              │                     ├─ Comment
              │                     ├─ Activity（変更履歴）
              │                     ├─ Watcher（User 多対多）
              │                     └─ Tag（多対多）
User ── Notification（アプリ内通知）
```

設計の検討過程は [`docs/jira-design.md`](docs/jira-design.md)、
既存の ToDo データから課題への移行手順は [`docs/issue-migration-runbook.md`](docs/issue-migration-runbook.md) にまとめています。

---

## 🔧 設計上のこだわり

### 1. コントローラを薄く保つ
入力の検証は `FormRequest`、権限の判定は `Policy`、データの絞り込みはモデルの**ローカルスコープ**、
複数のモデルにまたがる処理は**サービスクラス**（`WorkflowService` / `SprintService` / `IssueHierarchyService` など）に切り出し、
コントローラには「流れ」だけを残しています。

詳細画面のインライン編集も、**1 項目 = 1 コントローラ（`__invoke`）= 1 ルート**に分けました。
「全項目をまとめて受け取る update」を持たないので、項目ごとの検証・認可・履歴記録が混ざりません。

### 2. ステータス変更はワークフローを必ず通す
ステータスはプロジェクトごとに設定できるため、「どこからどこへ動かせるか」の判断が散らばると穴が開きます。
そこで遷移の可否は `WorkflowService` だけが判断し、
詳細画面・カンバンボード・一覧の完了チェックのどこから動かしても同じ判定を通るようにしました。

- 既定のワークフローは `To Do → In Progress → In Review → Done`
- 「着手していないものをレビューに出す」などの不自然な遷移は既定で禁止
- 集計（完了率など）はステータス名ではなく**カテゴリ（未着手 / 進行中 / 完了）**で行うため、
  プロジェクトごとにステータス名が違っても壊れない
- ステータスの削除は「残った課題をどこへ送るか」を選んでから実行する 2 段構え

### 3. 通知はイベントから組み立てる
課題の変更は `IssueObserver` からドメインイベント（`IssueCreated` / `IssueTransitioned` / `IssueAssigned` / `CommentPosted`）として発行し、
通知やウォッチの追加はリスナーが受け持ちます。変更履歴と同じ「モデルが変わったら必ず通る場所」から出すので、
新しい書き込み経路を足しても通知だけ漏れる、ということが起きません。今後の外部連携（Slack・Webhook）も同じイベントに乗せる想定です。

- イベントは**コミット後に発火**（`ShouldDispatchAfterCommit`）。ロールバックされた変更の通知は届かない
- **操作した本人には届けない**。移行コマンドやシーダーなど、操作者のいない変更も通知しない
- ウォッチが残っていても、**プロジェクトから外れた人には届けない**（見られなくなった課題の中身が通知経由で漏れないように）
- 通知の文面は送信時点の内容を写し取り、モデルを抱えない。キューに積んだあとで課題が消えても配信が落ちない
- アプリ内通知は同期で書き、**メールだけキューに回す**。ワーカーが止まっていても画面上の通知は届く
- メールは「担当になった」ときだけ。全部をメールにすると量が多すぎて読まれなくなるため

### 4. 他人のデータに触れさせない
認可の根拠は `project_members.role` の 1 か所だけに置いています。

| 操作 | admin | member | viewer |
|---|:-:|:-:|:-:|
| 課題の閲覧 | ○ | ○ | ○ |
| 課題の作成・編集 | ○ | ○ | × |
| 課題の削除 | ○ | 自分が起票したものだけ | × |
| ワークフロー設定・メンバー管理 | ○ | × | × |

「どの課題が見えるか」は Policy では守れないので、`Issue::scopeVisibleTo()` が担当します。
両者が同じ根拠を見ていることを専用のテストクラスで担保しています。
ワークフロー設定の URL も `scopeBindings()` で、そのプロジェクト配下のステータスしか解決しません。

### 5. 課題番号を重複させない
`PROJ-123` の連番は、同時に作成されても重複してはいけません。

- MySQL では `SELECT ... FOR UPDATE` でプロジェクトの行を掴んで採番
- SQLite では `FOR UPDATE` が効かないため、`unique(project_id, issue_number)` 制約とリトライで受け止める
- `pcntl` で**本当にプロセスを並行させる**テストで、重複しないことを確認

### 6. 既存データを壊さずに作り替える
個人向け ToDo（`tasks` / `subtasks` / 固定 3 ステータス）から課題管理への移行は、
マイグレーションを段階に分け、間に移行コマンドを挟む構成にしました。

```
カラム追加 → issues:migrate-from-tasks → NOT NULL 化・旧テーブル削除
          → workflows:install → 旧ステータス列の削除
```

移行が済んでいない状態で先へ進もうとすると、マイグレーションが**意図的に止まる**ガードを入れています。
Docker のエントリポイントはこの順序を自動で踏むので、既存のデータベースでもそのまま起動できます。
移行前の URL（`/tasks/{id}`）は課題キーの URL へ転送します。

### 7. 「書き留めるまで」を最短にする
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

- **入力が消えないこと** ― 解釈できなかった部分はすべてタイトルに残す
- **解釈結果を必ず見せること** ― 「『請求書を送る』を追加しました（期限 9/21(月) / 優先度高 / タグ 仕事）」のように返す
- **日本語入力への対応** ― 全角の `＃` `！` `３日後` も半角に正規化してから解析
- **JavaScript に依存しないこと** ― 解析はサーバー側。通常のフォーム送信で完結

解析ロジックは `QuickAddParser` に切り出し、表記ごとに単体テストを書いています。
同じ入力欄をボードの各レーン下部にも置き、そこから追加した課題はそのレーンのステータスで末尾に入ります。

### 8. 状態を enum で型安全に扱う
課題タイプ・優先度・ロール・スプリントの状態・リンクの種類などは PHP の **backed enum**
（`App\Enums\IssueType` / `TaskPriority` / `ProjectRole` / `SprintState` / `IssueLinkType` など）で定義し、
モデルのキャスト・バリデーション（`Rule::enum()`）・画面のラベルと配色までを 1 か所に集約しました。
プロジェクトごとに変わるステータスだけは、enum ではなくテーブル（`statuses`）で持っています。

### 9. リッチテキストを安全に扱う
リッチテキストは「HTML をそのまま保存して表示する」機能なので、XSS の入口になり得ます。
そこで **保存の直前に必ずサニタイズされる**よう、モデルのミューテータに処理を寄せました。
コントローラ側の書き忘れでは素通りしません。課題の本文とコメントの両方に適用しています。

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
- サニタイズは重いので、コメント投稿には回数制限（30 回 / 分）を設定

### 10. セキュリティの基本を押さえる
- パスワードはハッシュ化して保存（`hashed` キャスト）
- ログイン成功時に**セッション ID を再生成**（セッション固定攻撃対策）
- ログイン失敗 5 回で一時的にロック（総当たり対策）
- エラーメッセージでメールアドレスの存在有無を区別しない（ユーザー列挙対策）
- 全フォームに CSRF トークン、Blade のエスケープで XSS 対策

### 11. JavaScript が無くても壊れない
インライン編集は `<details>` / `<summary>` で作っているので、JS 無しでも開閉して保存できます。
完了トグル・絞り込み・削除確認もフォーム送信で成立し、
リッチエディタは `<noscript>` で通常のテキストエリアにフォールバックします。
（カンバンとバックログのドラッグ＆ドロップのみ JS 必須です）

### 12. パフォーマンス
- 必要な関連は eager load し、開発環境では `Model::shouldBeStrict()` で N+1 を検出
- 子課題の進捗は `withCount` で集計し、一覧での N+1 を回避
- 重い依存（Tiptap / SortableJS）は**動的 import** で必要なページだけ読み込む
  （初期バンドル 56KB / エディタ 381KB / ボード・バックログ 37KB に分割）

### 13. グラフは配色まで根拠を持たせる
ダッシュボードのグラフは外部ライブラリを使わず、サーバー側で組み立てた **インライン SVG** です。

- 日別の完了数は**単一系列**なので 1 色（凡例は不要、タイトルが系列名を兼ねる）
- 優先度は「低 < 中 < 高」の**順序尺度**なので、同一色相の明→暗ランプを使用
- 配色はライト／ダークそれぞれの背景色に対して、明度・彩度・コントラストを検証して決定
- 色だけに頼らないよう、内訳は件数と割合を文字でも併記

---

## 🧪 テスト

```bash
cd laravel_app
php artisan test
```

```
Tests:  2 skipped, 516 passed (1421 assertions)
```

スキップの 2 件は、課題番号の並行採番テストのうち `pcntl` 拡張や MySQL が必要なものです（環境が揃えば実行されます）。

| テスト | 検証内容 |
|---|---|
| `Auth\*` | 登録・ログイン／ログアウト・セッション再生成・回数制限・デモログイン |
| `Project\*` | プロジェクトの CRUD・メンバー管理・ロールごとの認可・プロジェクト切り替え・ワークフロー設定 |
| `Issue\IssuePolicyTest` | ロールごとの閲覧・作成・編集・削除の可否 |
| `Issue\IssueKeyTest` / `BrowseTest` | キーの採番・`/browse/{key}` での表示・旧 URL の転送 |
| `Issue\IssueNumberConcurrencyTest` | 並行して作成しても番号が重複しないこと |
| `Issue\IssueHierarchyTest` / `SubIssueTest` | 親子関係の制約（階層は 1 段まで・自分自身を親にしない）、子課題の追加・付け外し |
| `Issue\IssueLinkTest` | 課題リンクの作成・重複防止・削除 |
| `Notification\IssueNotificationTest` | 誰に届き誰に届かないか（本人・未ウォッチ・プロジェクト外・操作者なし・ロールバック）、自動ウォッチ |
| `Notification\NotificationInboxTest` | 通知一覧・未読数・既読化・他人の通知の保護 |
| `Issue\WatchTest` | ウォッチと解除、ロールごとの可否 |
| `Issue\CommentTest` / `ActivityTest` | コメントの投稿・編集・削除、変更履歴の記録 |
| `Issue\MigrateTasksToIssuesTest` / `InstallWorkflowsTest` | 旧 ToDo データからの移行コマンド |
| `Sprint\*` | スプリントの開始・完了・持ち越し、バックログの振り分け、バーンダウン |
| `Task\*` | 作成・削除・インライン更新・クイック追加・絞り込み・本文のサニタイズ・他人の課題の保護 |
| `Tag\TagTest` | タグの CRUD、ユーザー単位の一意制約、付け外し、絞り込み |
| `BoardTest` / `DashboardTest` | ボードの遷移と並び順、ダッシュボードの集計 |
| `PageRenderTest` | 全画面の描画（データ 0 件のケースを含む）と 404 |
| `Unit\*` | ワークフローの判定、課題の期限判定、クイック追加の解析、リッチテキストのサニタイズ |

---

## 🚀 セットアップ

### Docker で起動する

```bash
docker compose up --build
# → http://localhost:8000
```

これだけで MySQL とキューのワーカーごと起動し、そのまま新規登録して使えます。
初回起動時に以下が自動で実行されます。

1. `.env` の生成と、compose で指定した設定（DB 接続先など）の反映
2. アプリケーションキーの発行
3. MySQL の起動待ち → マイグレーション（既存データがあれば移行コマンドも順に実行）
4. デモデータの投入（`SEED_DATABASE: "false"` で無効化）

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
php artisan migrate --seed   # デモデータを投入

php artisan serve
php artisan queue:work   # 別のターミナルで（通知メールの送信に使う）
```

`composer run dev` なら、サーバー・キューのワーカー・ログ・Vite をまとめて起動できます。
メールは既定で `storage/logs/laravel.log` に書き出されます（`MAIL_MAILER=log`）。

> 旧 ToDo 版のデータベースを引き継ぐ場合は、`migrate` の前に
> [`docs/issue-migration-runbook.md`](docs/issue-migration-runbook.md) の手順を確認してください。

---

## 🧑‍💻 デモアカウント

**ログイン画面の「デモアカウントでログイン」ボタンから、登録せずにそのまま体験できます。**
手入力する場合は `demo@example.com` / `password123` です。

`DemoUserSeeder` は「ログインした瞬間にどの画面も成立していること」を狙って、
ランダム任せにせず件数を設計しています。

| 作られるもの | 内容 |
|---|---|
| 課題 | 100 件（未着手・進行中・完了をバランスよく配分） |
| スプリント | 完了済み・進行中・予定の 3 本。進行中のスプリントにはストーリーポイント付きの課題を入れ、バーンダウンに起伏を出す |
| 完了履歴 | 過去 14 日に分散。直近 5 日は必ず 1 件以上入れて**連続達成日数**が途切れないようにする |
| 優先度・期限 | 内訳グラフが偏らないよう配分し、期限切れ・期限間近・余裕あり・未設定を混ぜる |
| 子課題 | 一部の課題に 2〜5 件（進捗バーの確認用） |
| コメント・リンク | 一部の課題にコメントと課題リンクを作成 |
| タグ | 6 種（仕事 / プライベート / 学習 / 至急 / 事務手続き / あとで読む） |
| 本文 | 約 8 割に見出し・リスト・チェックリスト入りのリッチテキスト |
| 別ユーザー | `other@example.com` を作成し、データが分離されていることを確認できる |

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
│   ├── Events/                 # IssueCreated / IssueTransitioned / IssueAssigned / CommentPosted
│   ├── Enums/                  # IssueType / TaskPriority / ProjectRole / SprintState など
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Task/           # インライン編集（1 項目 = 1 コントローラ）・クイック追加
│   │   │   ├── Issue/          # コメント・課題リンク・旧 URL の転送
│   │   │   ├── Project/        # メンバー・ステータス・遷移・プロジェクト切り替え
│   │   │   └── Sprint/         # スプリントのライフサイクル
│   │   └── Requests/           # バリデーション
│   ├── Listeners/              # 自動ウォッチ・通知の送信
│   ├── Models/                 # Organization / Project / Issue / Status / Sprint / Comment など
│   ├── Notifications/          # 担当・ステータス変更・コメントの通知
│   ├── Observers/              # 変更履歴の記録とイベントの発行
│   ├── Policies/               # ロールに基づく認可
│   ├── Services/               # Workflow / Sprint / IssueHierarchy / IssueLink など
│   └── Support/
│       ├── QuickAddParser.php  # 1 行入力の解析
│       ├── IssueReference.php  # 課題キーの解釈
│       └── RichText.php        # HTML のサニタイズと平文変換
├── resources/
│   ├── css/app.css             # デザイントークン・エディタ・グラフの配色
│   ├── js/features/            # editor.js（Tiptap）/ board.js・backlog.js（D&D）
│   └── views/
│       ├── components/         # Blade コンポーネント（インライン編集・エディタ・バッジ等）
│       ├── tasks/ · backlog/ · board/ · dashboard/
│       ├── projects/ · sprints/ · tags/ · auth/
│       └── layouts/ · partials/
├── database/
│   ├── seeders/                # DemoUserSeeder（スプリント・分析データ入りのデモアカウント）
│   └── migrations/             # ToDo → 課題管理への段階的な移行を含む
└── tests/
    ├── Feature/ · Unit/
docs/                           # 設計書・移行手順書
```

---

## 📸 スクリーンショット

> UI を全面的に刷新したため、スクリーンショットは撮り直しが必要です。
> `imags/` 配下の画像は旧デザインのものです。

---

## 🔭 今後の展望

- @メンション（通知の仕組みに乗せる）
- Slack 通知・汎用 Webhook の送信（イベントに乗せる）
- GitHub 連携（コミット・PR の課題キーから課題へ紐づけ、マージで自動完了）
- 期限が近い課題のリマインド（スケジューラ + 通知）
- JQL 風の検索クエリと、保存できるフィルター
- クイック追加の入力中プレビュー（打ちながら解釈結果を表示）
- REST API 化（Laravel Sanctum）とモバイル対応
- 全文検索エンジンの導入（Laravel Scout）
