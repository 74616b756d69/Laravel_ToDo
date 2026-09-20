<?php

namespace Tests\Feature\Issue;

use App\Enums\ActivityField;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
use App\Services\SprintService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * 変更履歴の自動記録。
 *
 * 記録漏れがないことが主題なので、追跡対象の全フィールドを 1 件ずつ確かめ、
 * さらに「追跡対象を増やしたらテストも増える」ように
 * ActivityField::trackedColumns() を総なめする網羅テストも置いている。
 */
class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '操作した人']);
        $this->project = Project::personalFor($this->user);
        $this->issue = Issue::factory()
            ->inProject($this->project, $this->user)
            ->inCategory(StatusCategory::Todo)
            ->create(['priority' => TaskPriority::Medium, 'story_points' => null]);

        // 作成時の 1 件を毎回除いて数えたいので、ここで消しておく…のではなく
        // テスト側で field を指定して引く（履歴は消せない設計のため）
    }

    private function named(string $name): Status
    {
        return $this->project->statuses()->where('name', $name)->sole();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Activity> */
    private function activities(ActivityField $field)
    {
        return $this->issue->activities()->where('field', $field)->get();
    }

    // --- 作成 ---------------------------------------------------------------

    public function test_作成が記録される(): void
    {
        $created = $this->activities(ActivityField::Created);

        $this->assertCount(1, $created);
        $this->assertSame('課題を作成しました。', $created->first()->describe());
    }

    // --- フィールドごとの記録 ---------------------------------------------------

    public function test_ステータスの変更が記録される(): void
    {
        $this->actingAs($this->user);

        app(WorkflowService::class)->transition($this->issue, $this->named('In Progress'));

        $activity = $this->activities(ActivityField::Status)->sole();

        $this->assertSame('To Do', $activity->old_value);
        $this->assertSame('In Progress', $activity->new_value);
        $this->assertSame($this->user->id, $activity->user_id);
        $this->assertSame('ステータスを「To Do」から「In Progress」に変更しました。', $activity->describe());
    }

    public function test_担当者の変更が記録される(): void
    {
        $this->actingAs($this->user);
        $newAssignee = User::factory()->create(['name' => '新しい担当']);

        // assignee_id は fillable から外してあるので forceFill で書く
        $this->issue->forceFill(['assignee_id' => $newAssignee->id])->save();

        $activity = $this->activities(ActivityField::Assignee)->sole();

        $this->assertSame('操作した人', $activity->old_value);
        $this->assertSame('新しい担当', $activity->new_value);
    }

    public function test_担当者の解除が記録される(): void
    {
        $this->actingAs($this->user);

        $this->issue->forceFill(['assignee_id' => null])->save();

        $activity = $this->activities(ActivityField::Assignee)->sole();

        $this->assertSame('操作した人', $activity->old_value);
        $this->assertNull($activity->new_value);
        $this->assertSame('担当者「操作した人」を解除しました。', $activity->describe());
    }

    public function test_優先度の変更が記録される(): void
    {
        $this->actingAs($this->user);

        $this->issue->update(['priority' => TaskPriority::High]);

        $activity = $this->activities(ActivityField::Priority)->sole();

        $this->assertSame('中', $activity->old_value);
        $this->assertSame('高', $activity->new_value);
    }

    public function test_スプリントの変更が記録される(): void
    {
        $this->actingAs($this->user);
        $sprint = Sprint::factory()->for($this->project)->create(['name' => 'Sprint 1']);

        app(SprintService::class)->assign($this->issue, $sprint);

        $activity = $this->activities(ActivityField::Sprint)->sole();

        $this->assertNull($activity->old_value);
        $this->assertSame('Sprint 1', $activity->new_value);
        $this->assertSame('スプリントを「Sprint 1」に設定しました。', $activity->describe());
    }

    public function test_ストーリーポイントの変更が記録される(): void
    {
        $this->actingAs($this->user);

        $this->issue->update(['story_points' => 5]);
        $this->issue->update(['story_points' => 8]);

        $activities = $this->activities(ActivityField::StoryPoints);

        $this->assertCount(2, $activities);
        $this->assertNull($activities[0]->old_value);
        $this->assertSame('5', $activities[0]->new_value);
        $this->assertSame('5', $activities[1]->old_value);
        $this->assertSame('8', $activities[1]->new_value);
    }

    /**
     * 追跡対象を増やしたときに、ここが落ちて気づけるようにする。
     *
     * trackedColumns() の全項目について、実際に値を動かして記録されることを見る。
     */
    public function test_追跡対象の全フィールドが記録される(): void
    {
        $this->actingAs($this->user);

        $sprint = Sprint::factory()->for($this->project)->create(['name' => '網羅用']);
        $assignee = User::factory()->create(['name' => '別の人']);

        // trackedColumns() のキーと、そこに入れる新しい値
        $changes = [
            'status_id' => $this->named('In Progress')->id,
            'assignee_id' => $assignee->id,
            'priority' => TaskPriority::High,
            'sprint_id' => $sprint->id,
            'story_points' => 13,
        ];

        // 想定漏れがあればここで気づける
        $this->assertSame(
            array_keys(ActivityField::trackedColumns()),
            array_keys($changes),
            'trackedColumns() が変わっています。このテストの $changes も更新してください。',
        );

        foreach (ActivityField::trackedColumns() as $column => $field) {
            $before = $this->activities($field)->count();

            // forceFill で直接書く。サービスや fillable に頼らず、
            // モデル経由の書き込みなら必ず拾えることを確かめたい
            $this->issue->forceFill([$column => $changes[$column]])->save();

            $this->assertSame(
                $before + 1,
                $this->activities($field)->count(),
                "{$column} の変更が履歴に残っていません",
            );
        }
    }

    public function test_一度の更新で複数の変更もすべて記録される(): void
    {
        $this->actingAs($this->user);
        $assignee = User::factory()->create(['name' => '別の人']);

        $this->issue->forceFill([
            'assignee_id' => $assignee->id,
            'priority' => TaskPriority::Low,
            'story_points' => 3,
        ])->save();

        $this->assertCount(1, $this->activities(ActivityField::Assignee));
        $this->assertCount(1, $this->activities(ActivityField::Priority));
        $this->assertCount(1, $this->activities(ActivityField::StoryPoints));
    }

    // --- 記録しないもの --------------------------------------------------------

    public function test_追跡対象外の変更は記録されない(): void
    {
        $this->actingAs($this->user);
        $before = $this->issue->activities()->count();

        $this->issue->update([
            'title' => '題名を変えただけ',
            'content' => '<p>本文も変えた</p>',
            'position' => 42,
            'due_date' => today()->addWeek(),
        ]);

        $this->assertSame($before, $this->issue->activities()->count());
    }

    public function test_同じ値で保存しても記録されない(): void
    {
        $this->actingAs($this->user);
        $before = $this->activities(ActivityField::Priority)->count();

        $this->issue->update(['priority' => $this->issue->priority]);

        $this->assertSame($before, $this->activities(ActivityField::Priority)->count());
    }

    // --- 操作者 -------------------------------------------------------------

    public function test_未ログインの変更はシステムとして記録される(): void
    {
        // コマンドやシーダーからの変更がこれにあたる
        $this->issue->update(['story_points' => 2]);

        $activity = $this->activities(ActivityField::StoryPoints)->sole();

        $this->assertNull($activity->user_id);
        $this->assertSame('システム', $activity->actorName());
    }

    public function test_画面からの操作も記録される(): void
    {
        $this->actingAs($this->user)->patch(route('tasks.completion', $this->issue));

        $activity = $this->activities(ActivityField::Status)->sole();

        $this->assertSame('To Do', $activity->old_value);
        $this->assertSame('Done', $activity->new_value);
        $this->assertSame($this->user->id, $activity->user_id);
    }

    /**
     * スプリント完了時の一括移送も、1 件ずつモデル経由で動かすので履歴に残る。
     *
     * クエリビルダの一括 update はモデルイベントが飛ばないため、
     * ここが最も記録漏れしやすい。
     */
    public function test_スプリント完了時の移送も記録される(): void
    {
        $this->actingAs($this->user);

        $sprint = Sprint::factory()->for($this->project)->active()->create(['name' => '走っているやつ']);
        app(SprintService::class)->assign($this->issue, $sprint);

        app(SprintService::class)->complete($sprint);

        $activities = $this->activities(ActivityField::Sprint);

        $this->assertCount(2, $activities);
        // 入れたとき
        $this->assertSame('走っているやつ', $activities[0]->new_value);
        // 完了で押し出されたとき
        $this->assertSame('走っているやつ', $activities[1]->old_value);
        $this->assertNull($activities[1]->new_value);
    }

    // --- 不変であること --------------------------------------------------------

    public function test_履歴は更新できない(): void
    {
        $activity = $this->activities(ActivityField::Created)->sole();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('履歴は変更できません');

        $activity->update(['new_value' => '改ざん']);
    }

    public function test_履歴は削除できない(): void
    {
        $activity = $this->activities(ActivityField::Created)->sole();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('履歴は削除できません');

        $activity->delete();
    }

    public function test_履歴にupdated_atは無い(): void
    {
        // 更新という概念が無いことをスキーマでも示す
        $this->assertNotContains('updated_at', \Illuminate\Support\Facades\Schema::getColumnListing('activities'));
        $this->assertNull(Activity::UPDATED_AT);
    }

    public function test_履歴を書き換えるルートが無い(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'activit'));

        $this->assertTrue($routes->isEmpty(), '履歴を操作するルートが生えています: '.$routes->implode(', '));
    }

    public function test_課題を完全に削除すると履歴も消える(): void
    {
        $id = $this->issue->id;

        // 不変なのはアプリからの操作に対してであって、
        // 親が物理削除されたら外部キーのカスケードで一緒に消える
        $this->issue->forceDelete();

        $this->assertSame(0, DB::table('activities')->where('issue_id', $id)->count());
    }
}
