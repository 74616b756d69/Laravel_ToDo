<?php

namespace Tests\Unit;

use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Status;
use Tests\TestCase;

class IssueTest extends TestCase
{
    /**
     * DB を使わずに課題を組み立てる。
     * 完了判定はステータスのカテゴリを見るので、関連だけ差し込む。
     */
    private function issue(StatusCategory $category, ?string $dueDate): Issue
    {
        $issue = new Issue(['due_date' => $dueDate]);

        return $issue->setRelation('status', new Status(['name' => 'テスト', 'category' => $category]));
    }

    public function test_未完了で期限を過ぎていれば期限切れになる(): void
    {
        $task = $this->issue(StatusCategory::Todo, today()->subDay()->toDateString());

        $this->assertTrue($task->isOverdue());
    }

    public function test_進行中でも期限を過ぎていれば期限切れになる(): void
    {
        $task = $this->issue(StatusCategory::InProgress, today()->subDay()->toDateString());

        $this->assertTrue($task->isOverdue());
    }

    public function test_完了済みなら期限を過ぎていても期限切れにしない(): void
    {
        $task = $this->issue(StatusCategory::Done, today()->subDay()->toDateString());

        $this->assertFalse($task->isOverdue());
    }

    public function test_期限が未設定なら期限切れにならない(): void
    {
        $task = $this->issue(StatusCategory::Todo, null);

        $this->assertFalse($task->isOverdue());
    }

    public function test_期限が3日以内なら間近と判定する(): void
    {
        $task = $this->issue(StatusCategory::Todo, today()->addDay()->toDateString());

        $this->assertTrue($task->isDueSoon());
        $this->assertFalse($task->isOverdue());
    }

    /**
     * 完了かどうかは名前ではなくカテゴリで決まる。
     * 「Done」を別名に変えても壊れないことを固定しておく。
     */
    public function test_完了判定はステータス名に依存しない(): void
    {
        $issue = new Issue;
        $issue->setRelation('status', new Status([
            'name' => 'リリース済み',
            'category' => StatusCategory::Done,
        ]));

        $this->assertTrue($issue->isCompleted());
    }
}
