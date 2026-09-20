<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuickAddTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_一行の入力からタスクを作れる(): void
    {
        $tag = Tag::factory()->for($this->user)->create(['name' => '仕事']);

        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '明日 請求書を送る #仕事 !高'])
            ->assertRedirect();

        $task = Task::sole();

        $this->assertSame('請求書を送る', $task->title);
        $this->assertSame('2026-09-25', $task->due_date->toDateString());
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertTrue($task->tags->contains($tag));
    }

    public function test_記法が無ければタイトルだけのタスクになる(): void
    {
        $this->actingAs($this->user)->post(route('tasks.quick'), ['quick' => '牛乳を買う']);

        $task = Task::sole();

        $this->assertSame('牛乳を買う', $task->title);
        $this->assertNull($task->due_date);
        $this->assertSame(TaskPriority::Medium, $task->priority);
    }

    public function test_どう解釈したかがメッセージで返る(): void
    {
        Tag::factory()->for($this->user)->create(['name' => '仕事']);

        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '明日 請求書を送る #仕事 !高'])
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '請求書を送る')
                && str_contains($message, '9/25')
                && str_contains($message, '優先度高')
                && str_contains($message, '仕事'));
    }

    public function test_未登録のタグは無視して知らせる(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '買い物 #未登録'])
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '未登録のタグは無視しました'));

        $this->assertTrue(Task::sole()->tags->isEmpty());
    }

    public function test_他人のタグは使えない(): void
    {
        Tag::factory()->for(User::factory())->create(['name' => '他人のタグ']);

        $this->actingAs($this->user)->post(route('tasks.quick'), ['quick' => '作業 #他人のタグ']);

        $this->assertTrue(Task::sole()->tags->isEmpty());
    }

    public function test_内容が空ならタスクを作らない(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '明日 !高'])
            ->assertSessionHasErrors('quick');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_未入力では送信できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => ''])
            ->assertSessionHasErrors('quick');
    }

    public function test_長すぎる入力は弾かれる(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => str_repeat('あ', 201)])
            ->assertSessionHasErrors('quick');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_未ログインでは使えない(): void
    {
        $this->post(route('tasks.quick'), ['quick' => '作業'])->assertRedirect(route('login'));
    }

    public function test_一覧にクイック追加の入力欄が表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('name="quick"', false);
    }

    public function test_ボードのレーンを指定して追加できる(): void
    {
        $this->actingAs($this->user)->post(route('tasks.quick'), [
            'quick' => '明日 レビューを依頼する !高',
            'status' => TaskStatus::Doing->value,
        ])->assertRedirect();

        $task = Task::sole();

        $this->assertSame(TaskStatus::Doing, $task->status);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertNull($task->completed_at);
    }

    public function test_完了レーンに追加すると完了日時が入る(): void
    {
        $this->actingAs($this->user)->post(route('tasks.quick'), [
            'quick' => '対応済みの作業',
            'status' => TaskStatus::Done->value,
        ]);

        $this->assertNotNull(Task::sole()->completed_at);
    }

    public function test_追加したタスクはそのレーンの末尾に並ぶ(): void
    {
        Task::factory()->count(3)->for($this->user)->create([
            'status' => TaskStatus::Todo,
            'position' => 0,
        ]);

        $this->actingAs($this->user)->post(route('tasks.quick'), [
            'quick' => '最後に足したタスク',
            'status' => TaskStatus::Todo->value,
        ]);

        $order = $this->user->tasks()
            ->where('status', TaskStatus::Todo)
            ->orderBy('position')
            ->orderByDesc('id')
            ->pluck('title');

        $this->assertSame('最後に足したタスク', $order->last());
    }

    public function test_ボードから追加するとそのカードの位置へ戻る(): void
    {
        $this->from(route('board'))->actingAs($this->user)->post(route('tasks.quick'), [
            'quick' => '追加したタスク',
            'status' => TaskStatus::Todo->value,
        ])->assertRedirect(route('board').'#task-'.Task::sole()->id);
    }

    public function test_一覧からの追加ではフラグメントを付けない(): void
    {
        $this->from(route('tasks.index'))->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '追加したタスク'])
            ->assertRedirect(route('tasks.index'));
    }

    public function test_レーン指定時はステータスも通知に含まれる(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), [
                'quick' => '調査する',
                'status' => TaskStatus::Doing->value,
            ])
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '進行中'));
    }

    public function test_不正なレーンは受け付けない(): void
    {
        $this->actingAs($this->user)
            ->post(route('tasks.quick'), ['quick' => '作業', 'status' => 'unknown'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_ボードの各レーンに追加フォームがある(): void
    {
        $response = $this->actingAs($this->user)->get(route('board'))->assertOk();

        foreach (TaskStatus::cases() as $status) {
            $response->assertSee('<input type="hidden" name="status" value="'.$status->value.'">', false);
        }
    }
}
