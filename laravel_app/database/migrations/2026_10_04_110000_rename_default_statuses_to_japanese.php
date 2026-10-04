<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 既定ワークフローのステータス名を日本語にする。
 *
 * 画面は日本語なのに、ステータスだけが「TO DO / IN PROGRESS」と英語で並んでいた。
 * 件数タブの「未着手・進行中」とも呼び名が食い違うので、既定の名前を揃える。
 *
 * 変えるのは既定の英語名のままのものだけ。管理者が付け直した名前には触れない。
 * 同じプロジェクトにすでに日本語名のステータスがある場合も、
 * 一意制約（project_id, name）に当たるので飛ばす。
 *
 * 完了判定や集計は名前ではなくカテゴリで行っているので、名前を変えても挙動は変わらない。
 * 変更履歴（activities）に残っている旧名は、その時点の記録なのでそのままにする。
 */
return new class extends Migration
{
    private const NAMES = [
        'To Do' => '未着手',
        'In Progress' => '進行中',
        'In Review' => 'レビュー中',
        'Done' => '完了',
    ];

    public function up(): void
    {
        $this->rename(self::NAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::NAMES));
    }

    /**
     * @param  array<string, string>  $names  旧名 → 新名
     */
    private function rename(array $names): void
    {
        foreach ($names as $from => $to) {
            $taken = DB::table('statuses')->where('name', $to)->pluck('project_id');

            DB::table('statuses')
                ->where('name', $from)
                ->whereNotIn('project_id', $taken)
                ->update(['name' => $to]);
        }
    }
};
