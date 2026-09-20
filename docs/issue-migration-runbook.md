# Task → Issue / ワークフロー移行 手順書（フェーズ2・3）

既存の `tasks` を課題（Issue）へ移し、固定 3 ステータスをプロジェクトごとの
ワークフローへ置き換える作業の実施手順。

マイグレーションは 6 本に分かれていて、**間に移行コマンドを 2 回挟む**必要がある。
何も考えずに `php artisan migrate` を通すと M3 と M6 で意図的に止まる。

| 記号 | ファイル | 内容 | 可逆 |
| --- | --- | --- | --- |
| M1 | `2026_09_21_100000_add_issue_columns_to_tasks_table` | カラム追加のみ | ○ |
| M3 | `2026_09_21_100100_tighten_issue_columns_on_tasks_table` | NOT NULL 化 | ○ |
| M4 | `2026_09_21_100200_drop_subtasks_table_and_user_id_from_tasks` | `subtasks` と `tasks.user_id` を削除 | **×** |
| M5 | `2026_09_21_110000_create_statuses_and_transitions_tables` | `statuses` / `transitions` 作成、`tasks.status_id` 追加 | ○ |
| M6 | `2026_09_21_110100_tighten_status_id_on_tasks_table` | `status_id` を NOT NULL 化 | ○ |
| M7 | `2026_09_21_110200_drop_status_column_from_tasks_table` | 旧 `tasks.status` を削除 | **×** |
| M8 | `2026_09_21_120000_create_sprints_table` | `sprints` 作成、`tasks.sprint_id` / `story_points` 追加 | ○ |
| M9 | `2026_09_21_130000_create_comments_and_activities_tables` | `comments` / `activities` 作成 | ○ |

フェーズ 3（M5〜M7）の間には `workflows:install` を挟む。全体の順序は:

```
M1 → issues:migrate-from-tasks → M3 → M4 → M5 → workflows:install → M6 → M7 → M8
```

M8（スプリント）と M9（コメント・履歴）は追加だけなので移行コマンドは要らない。
`php artisan migrate` で通る。

なお M9 より前の変更は履歴に残らない。遡って記録することはできないので、
既存の課題は「履歴なし」から始まる。

---

## 実施手順

### 0. バックアップ

```bash
# SQLite
cp database/database.sqlite database/database.sqlite.$(date +%Y%m%d%H%M).bak
# MySQL
mysqldump -u root -p dev > dev-$(date +%Y%m%d%H%M).sql
```

M4 を適用したあとは戻せない。ここを飛ばさないこと。

### 1. M1 を適用する

```bash
php artisan migrate --step
```

M1 が適用されたあと、**M3 が次の例外で必ず止まる**。これは想定どおり。

```
RuntimeException: 未移行のタスクが 119 件あります。
先に php artisan issues:migrate-from-tasks を実行してください。
```

この時点でアプリは旧コードのままでも動く（追加したカラムはすべて nullable）。

### 2. 影響範囲を確認する

```bash
php artisan issues:migrate-from-tasks --dry-run
```

```
移行前: tasks=119 / subtasks=88 / tag_task=107 / orphan_tasks=119 / pending_subtasks=88
  - デモユーザー: タスク 90 件を移行（dry-run）
  - Takumin: タスク 29 件を移行（dry-run）
```

### 3. 移行する

```bash
php artisan issues:migrate-from-tasks
```

```
  - デモユーザー → DEMO: 課題 90 件 / サブタスク 88 件
  - Takumin → TAKUMIN: 課題 29 件 / サブタスク 0 件
移行後: tasks=207 / subtasks=88 / tag_task=107 / orphan_tasks=0 / pending_subtasks=0
移行が完了しました。データの欠損はありません。
```

コマンドは冪等なので、途中で落ちたらそのまま再実行してよい。
`subtasks` テーブルには一切手を付けないので、この時点でもまだ完全に戻せる。

**「データの欠損はありません」が出なければ先に進まないこと。** 非ゼロ終了する。

### 4. M3 を適用する

```bash
php artisan migrate --step
```

`project_id` / `issue_number` / `reporter_id` が NOT NULL になる。

### 5. アプリを新コードでデプロイして動作確認

一覧・ボード・課題詳細・サブタスクの追加と完了・分析画面が開くこと。
課題キー（`PROJ-123`）が一覧と詳細に出ていること。

### 6. M4・M5 を適用する

```bash
php artisan migrate
```

M4 で `subtasks` と `tasks.user_id` が消える（**不可逆**。バックアップ必須）。
続けて M5 が `statuses` / `transitions` を作り、M6 の手前でまた止まる:

```
RuntimeException: ステータス未移行の課題が 207 件あります。
先に php artisan workflows:install を実行してください。
```

### 7. ワークフローを導入する

```bash
php artisan workflows:install --dry-run
php artisan workflows:install
```

```
  - DEMO: 課題 178 件 / ステータス 4 件
  - TAKUMIN: 課題 29 件 / ステータス 4 件
ワークフローの導入が完了しました。データの欠損はありません。
```

各プロジェクトに To Do / In Progress / In Review / Done と 8 本の遷移が入り、
旧 `status` が対応するステータスへ移る（`todo`→To Do / `doing`→In Progress / `done`→Done）。
`In Review` は新設なので、移行直後は空。

### 8. M6・M7 を適用する（M7 は不可逆）

```bash
php artisan migrate
```

---

## ロールバック

### フェーズ 3 を戻す（M7 を適用する前なら完全に戻せる）

**順序が重要。** 先にマイグレーションを戻してからコマンドを実行する。
`status_id` が NOT NULL のまま巻き戻そうとすると失敗する。

```bash
# 1. M7（status の復活）と M6（NOT NULL の解除）を戻す
php artisan migrate:rollback --step=2

# 2. status_id のカテゴリから旧 status を書き戻し、ワークフローを消す
php artisan workflows:rollback --dry-run
php artisan workflows:rollback

# 3. M5 を戻す
php artisan migrate:rollback --step=1
```

M7 の `down()` は `status` 列を既定値つきで作り直すだけなので、
値の復元は `workflows:rollback` が担当する（`status_id` のカテゴリから逆引きする）。
**In Review に居た課題だけは `doing` に寄る** — 旧 3 値に対応する値が無いため。
カテゴリは同じなので画面上の扱いは変わらないが、「レビュー中だった」区別は失われる。

### フェーズ 2 を戻す（M4 を適用する前なら完全に戻せる）

```bash
# 1. 移送した子課題を消し、親の新カラムを NULL に戻す
php artisan issues:rollback-migration --dry-run   # 影響範囲の確認
php artisan issues:rollback-migration

# 2. スキーマを戻す（M3 を適用済みなら --step=2）
php artisan migrate:rollback --step=1
```

`subtasks` テーブルは移行中ずっと無傷なので、これで移行前とまったく同じ状態に戻る。
実地検証済み: 巻き戻したあと、もう一度 `issues:migrate-from-tasks` を流せる。

### M4 を適用したあと（戻せない）

`subtasks` と `tasks.user_id` が物理削除されているため、**手順 0 のバックアップから復元する以外に方法はない**。
`issues:rollback-migration` は状況を検知して、何もせずエラーで終了する。

---

## 実施済みの検証

| 項目 | 結果 |
| --- | --- |
| 実データ相当（119 タスク / 88 サブタスク / 107 タグ紐付け / ソフトデリート 2 件）での通し実行 | 親 119 + 子 88 = 207 件、タグ 107 件、欠損 0、番号重複 0、孤児 0 |
| 移行コマンドの二重実行 | 結果が変わらない |
| `--dry-run` | 何も変更しない |
| ロールバック → 再移行 | 元の状態に戻り、再移行できる |
| M3 のガード（未移行時） | 例外で停止する |
| ワークフロー移行（119 親 + 88 子 / todo 84・doing 40・done 83） | 件数と内訳が完全に一致、欠損 0、孤児 0 |
| `workflows:install` の二重実行 / `--dry-run` | 結果が変わらない / 何も変更しない |
| ワークフローの巻き戻し → 再導入 | 旧 status が 84/40/83 で復元され、再導入できる |
| M6 のガード（未導入時） | 例外で停止する |
| M8（スプリント追加）を 119 親 + 88 子に適用 | 件数そのまま、孤児 0。ロールバックしても同じ |
| M9（コメント・履歴追加）を 119 親 + 88 子に適用 | 件数そのまま、孤児 0。ロールバックしても同じ |
| SQLite での全テスト | 386 passed / 2 skipped |
| MySQL 8.3 での全テスト | 388 passed（`FOR UPDATE` の並行テストを含む） |

---

## 注意点

### SQLite のテーブル再構築で子課題が消える問題

`tasks` は `parent_id` で自分自身を `ON DELETE CASCADE` 参照している。
SQLite のカラム定義変更・外部キー削除は「新テーブルを作る → 旧 `tasks` を drop → rename」という
再構築で実現されるが、このとき新テーブルの `parent_id` はまだ**旧テーブル**を参照している。
そのため `drop table tasks` の暗黙 DELETE が CASCADE を発火させ、コピー済みの子課題を消してしまう。

`PRAGMA foreign_keys` はトランザクション内では効かないため、
M3 / M4 は**再構築の前後で親子リンクを一時的に外して**この問題を回避している
（各マイグレーションの `withoutParentLinks()`）。
`tasks` に自己参照の外部キーを足す／変えるマイグレーションを書くときは、同じ配慮が要る。
**外部キーの「追加」でも再構築は起きる**ので、M5 の `status_id` 追加にも同じ保護を入れてある。

### MySQL でのインデックス削除順

MySQL は外部キーが使っているインデックスを先に削除できない。
M4（`user_id`）と M8 の `down()`（`sprint_id`）は
「**外部キー → インデックス → カラム**」の順で落としている。
この順序だけが SQLite と MySQL の両方で通る。

### サブタスクの判定は parent_id

「その課題が一覧・ボード・バックログに単独で並ぶか」は
**`parent_id` が NULL かどうかだけ**で決まる（`Issue::scopeTopLevel()`）。
`issue_type` は Bug / Story / Task という課題の性質を表すもので、親子関係とは別の軸。

そのため既存の Bug を他の課題のサブタスクにしても Bug のままで、外せば元どおり一覧に戻る。
階層は 1 段までに制限してあり、「親は子であってはならない」「子は子を持っていてはならない」の
2 条件で循環も同時に塞いでいる（`IssueHierarchyService`）。

`tasks.parent_id` を触る処理を足すときは、`issue_type` を書き換えないこと。

### 変更履歴の記録漏れ

`activities` への記録は `IssueObserver` が Eloquent の `created` / `updated` で行う。
**クエリビルダの一括 update はモデルイベントが飛ばない**ため、
`Issue::whereIn(...)->update([...])` のような書き方をすると履歴が残らない。

実際にスプリント完了時の未完了課題の移送がこの形になっていたので、
1 件ずつモデル経由で動かすように直してある（`SprintService::carryOverIncomplete()`）。
追跡対象のカラムを触る処理を追加するときは、必ずモデル経由で書くこと。

追跡対象は `ActivityField::trackedColumns()` の 1 箇所で決まる。
ここを増やすと `ActivityTest::test_追跡対象の全フィールドが記録される` が落ちるので、
テストを足し忘れたことに気づける。

### 「同時に active なスプリントは 1 つ」の担保

`sprints.active_marker` は active のときだけ 1 が入り、それ以外は null。
`unique(project_id, active_marker)` を張ってあり、NULL 同士は衝突しないので
active でないスプリントはいくつでも並べられる。
MySQL も SQLite も部分インデックスを同じ形では書けないため、この手を使っている。
アプリ側（`SprintService::start()`）でも確認しているが、
並行リクエストをすり抜けた場合はこの制約が最後の砦になる。

### MySQL でのテスト実行

`FOR UPDATE` が実際に効くことを確かめられるのは MySQL だけ。
SQLite では黙って無視されるため、SQLite で証明できるのは「重複が起きないこと」までになる。

```bash
docker compose up -d db
docker compose exec db mysql -uroot -ppassword -e "create database if not exists testing"
php artisan test -c phpunit-mysql.xml
```
