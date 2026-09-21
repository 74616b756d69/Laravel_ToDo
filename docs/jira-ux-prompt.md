# 実装依頼プロンプト — Jira らしい使い勝手を仕上げる

> このファイルはそのままコーディングエージェント（Claude Code 等）に貼って使うための指示書です。
> 対象リポジトリ: `laravel_app/`（Laravel 12 / Blade / Tailwind v4 / SQLite・MySQL）

---

## あなたへの依頼

このリポジトリは `docs/jira-design.md` の設計に沿って、個人向け ToDo アプリから
最小限の Jira クローンへの作り替えが Phase 3 まで進んでいます。
データモデル（Organization / Project / Issue / Status / Transition / Sprint / Comment / Activity / IssueLink）と
認可（`project_members.role` ベースの Policy とクエリスコープ）は揃っている一方で、
**画面からその機能に手が届いておらず、まだ「1 人用の ToDo アプリ」の操作感**です。

このギャップを埋めて、Jira を触っている感覚に近づけてください。

---

## 現状の把握（調査済み。再調査は不要だが、着手前に裏は取ること）

### 実装済みのもの

| 領域 | 状態 |
| --- | --- |
| ドメインモデル | `Issue`（テーブルは `tasks` のまま）/ `Project` / `Status` / `Transition` / `Sprint` / `Comment` / `Activity` / `IssueLink` すべて実装済み |
| 課題キー | `Issue::key()` が `PROJ-123` を組み立てる。採番は `Project::createIssue()` がロック + unique + リトライで担保 |
| ワークフロー | `WorkflowService` が遷移可否を判定。`availableFor()` で「次に行ける状態」も取れる |
| 認可 | `IssuePolicy` / `ProjectPolicy` / `CommentPolicy` / `SprintPolicy` + `Issue::scopeVisibleTo()` |
| 履歴 | `IssueObserver` が status / assignee / priority / sprint / story_points の変更を `activities` に記録 |
| 画面 | タスク一覧・詳細（タイムライン、サブタスク、リンク）・ボード・バックログ・スプリント・プロジェクト一覧/設定・タグ・分析 |
| テスト | Feature + Unit で **417 件 pass**（`php artisan test`、約 10 秒） |

### 使い勝手のギャップ（これを埋めるのが今回の仕事）

1. **プロジェクトを切り替えられない。**
   `BoardController` / `BacklogController` / `TaskController@create` / `TaskRequest::project()` がいずれも
   `Project::personalFor(Auth::user())` を直接呼んでいる。
   プロジェクトを作っても、招待されても、ボードにもバックログにも出てこない。作成した課題も必ず個人プロジェクトに入る。
2. **担当者を変えられない。**
   `assignee_id` カラムも `Activity` の記録も関連もあるのに、`TaskController@store` が
   `'assignee_id' => $request->user()->id` と固定しており、UI が一切ない。
3. **課題タイプを選べない。**
   `IssueType`（epic / story / task / bug / subtask）はラベルもアイコンも色もあるのに、
   `Issue::$fillable` に含まれておらず、フォームにも項目がない。実質すべて `task` になる。
4. **詳細画面でステータスを変えられない。**
   `WorkflowService::availableFor()` があるのに、詳細画面から使っているのは
   「完了にする / 未着手に戻す」のトグルだけ。状態を動かすには編集フォームを開く必要がある。
5. **課題キーで辿り着けない。**
   `PROJ-123` は表示されるのにリンクにも検索にもならない。`/browse/{key}` 相当のルートがなく、
   グローバル検索窓もない（一覧ページの中の絞り込みフォームだけ）。
6. **ワークフローを編集できない。**
   `statuses` / `transitions` はプロジェクト単位のマスタなのに、プロジェクト設定画面
   （`projects/edit.blade.php`）は名前・キー・説明とメンバー招待のみ。
   既定の 4 ステータスから動かせない。

---

## 実装してほしいこと

優先度順。**上から順に、1 つずつ完成させてからつぎへ進んでください。**
各段階で `php artisan test` が全件 pass する状態を保ってください。

### 1. プロジェクトコンテキスト（最優先）

- 「いま見ているプロジェクト」をセッションに保持する仕組みを 1 箇所に作る
  （例: `App\Support\ProjectContext` に `current(User): Project` / `switchTo(Project)` / `available(User)`）。
- セッションの値は信用しない。毎回「自分が所属しているプロジェクトか」を検証し、
  外れていたら既定（いちばん古い所属プロジェクト。どこにも居なければ `Project::personalFor()`）へ落とす。
- ヘッダーにプロジェクト切り替え UI を置く。切り替えたら**元居た画面に戻る**こと
  （ボードで切り替えたらボードのまま中身が差し替わる）。
- `BoardController` / `BacklogController` / `TaskController@create,@store` / `TaskRequest::project()` から
  `Project::personalFor()` の直呼びを消し、このコンテキスト経由にする。
- タスク一覧（`TaskController@index`）は**横断ビューのまま残す**。
  ただし絞り込みにプロジェクト選択を足し、カードにプロジェクトキーが見えるようにする。
  （Jira の「Your work」に相当。ここを強制的に単一プロジェクトへ絞ると既存テストの前提も壊れる）

**受け入れ条件**
- 2 つのプロジェクトに所属するユーザーが、ヘッダーから切り替えるとボード・バックログの内容が入れ替わる。
- 切り替えた状態で作成した課題が、そのプロジェクトに `PROJ-n` で採番されて入る。
- 所属していないプロジェクトへの切り替えは 403。

### 2. 詳細画面のインライン操作

`resources/views/tasks/show.blade.php` を、編集フォームへ行かずに主要な変更ができる画面にする。

- **ステータス遷移ボタン**: `WorkflowService::availableFor($task)` の結果をボタンとして並べる。
  押すと 1 リクエストで遷移する専用ルート（例 `PATCH tasks/{task}/transition`）。
  禁止された遷移は `IllegalTransitionException` が既に 422 を返す作りなので、それに合わせる。
- **担当者の変更**: そのプロジェクトのメンバー（`$project->users`）から選ぶ。未割り当ても選べること。
  「自分に割り当てる」のワンクリックも用意する。専用ルート（例 `PATCH tasks/{task}/assignee`）。
- **課題タイプの変更**: `IssueType` を選べるようにする。`Issue::$fillable` への追加が必要。
  ただし `parent_id` との整合（サブタスクかどうかは `isChild()` が見る）を壊さないこと。
- 詳細の `<dl>` に **担当者・起票者・スプリント・ストーリーポイント**を表示する（今は日付 4 つだけ）。
- 作成・編集フォーム（`tasks/form.blade.php`）にも担当者と課題タイプを足す。

**守ること**
- ステータスの書き換えは**必ず `WorkflowService::transition()` 経由**。`Issue::update(['status_id' => ...])` は禁止
  （`Issue` モデルの docblock にもそう書いてある）。
- 担当者は `project_members` に居る人しか選べない。`Rule::exists` をプロジェクトで絞ること。
- 変更が `activities` に載ることを確認する（`IssueObserver` はモデル経由の保存にしか反応しない。
  クエリビルダの一括 update を使わない）。

### 3. 課題キーでの移動と全体検索

- `GET /browse/{key}`（例 `/browse/ABC-12`）で該当課題の詳細へリダイレクト。
  見えない課題・存在しないキーは 404。解決には既存の `App\Support\IssueReference` を再利用する
  （いまはプロジェクトを渡す前提なので、横断で引けるよう拡張してよい）。
- ヘッダーにグローバル検索窓を置く。入力が課題キーの形なら `/browse` へ直行、
  そうでなければタスク一覧のキーワード検索へ流す。
- 一覧・ボード・詳細に出ている課題キー（`PROJ-123`）をリンクにする。

### 4. ワークフロー設定（余力があれば）

プロジェクト設定画面（admin のみ）で `statuses` と `transitions` を編集できるようにする。

- ステータスの追加・改名・並べ替え・削除（`category` は todo / in_progress / done から選ぶ）。
- 削除しようとしたステータスに課題が残っている場合は、移送先を選ばせてから消す
  （スプリント完了が同じ「2 段構え」を採っているので、`SprintController@confirmComplete` の作りに揃える）。
- 遷移の追加・削除。`from_status_id` が null の行は「どの状態からでも」。
- 既定ワークフローは `WorkflowService::DEFAULT_STATUSES` / `DEFAULT_TRANSITIONS` にある。
- プロジェクトは必ず初期ステータスと done ステータスを 1 つ以上持つ（`initialStatus()` / `doneStatus()` が
  `firstOrFail()` なので、空にできてしまうと全画面が落ちる）。**これを壊さない検証を必ず入れる。**

---

## 全体を通して守ってほしい制約

- **`Model::shouldBeStrict()` が有効**（非本番）。遅延ロードは例外になる。
  関連を増やしたら、その画面のクエリに `with()` / `withCount()` を必ず足すこと。
- **書き込みの入口を増やさない。** ステータスは `WorkflowService`、スプリントは `SprintService`、
  課題の生成は `Project::createIssue()`、リンクは `IssueLinkService`、階層は `IssueHierarchyService`。
  コントローラからモデルを直接 `forceFill` しない。
- **`Issue::$fillable` は利用者が直接決めてよい項目だけ。** 外部キーを安易に足さない
  （`issue_type` は利用者が決める項目なので追加してよい／`status_id` はだめ）。
- **認可は二重に張る。** 一覧・ボード・検索はクエリスコープ（`visibleTo`）、
  個別の操作は Policy。片方だけにしない。
- **JS 無効でも壊れない。** 既存画面は素の form 送信で完結し、JS は増補としてしか使っていない。
  この方針を踏襲する（`data-auto-submit` / `data-confirm` の既存パターンを見ること）。
- **UI は既存のトーンに合わせる。** `resources/views/components/` の
  `badge` / `icon` / `empty-state` / `page-heading` / `stat-card` を使い回す。
  新しいアイコンは `components/icon.blade.php` の `$paths` に足す。ダークモード対応も必須。
- **日本語のコメントは「なぜ」を書く。** 既存コードのコメントの粒度と語り口に合わせること。
- **テストを足す。** 追加した動線ごとに Feature テストを書く。既存 417 件を 1 件も落とさない。
  テストの日本語メソッド名の付け方は既存に倣う。`tests/Concerns/UsesWorkflow.php` が使える。
- **`vendor/bin/pint` を通す。**
- **コミットはしない。** 変更は作業ツリーに残したまま、何をしたか報告すること。

---

## 作業の進め方

1. 着手前に `php artisan test` を走らせてベースライン（417 pass）を確認する。
2. 上の 1 → 2 → 3 の順に、段階ごとに テスト追加 → 全件 pass → 報告 のサイクルで進める。
3. 各段階の終わりに、変更したファイルと、意図的に手を付けなかったことを列挙する。
4. 途中で設計判断に迷ったら、`docs/jira-design.md` の該当章（特に「採用しなかった案」）を先に読む。
   そこに答えが書かれていることが多い。書かれていない判断が必要なら、勝手に決めずに聞く。
