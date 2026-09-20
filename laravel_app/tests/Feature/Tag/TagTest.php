<?php

namespace Tests\Feature\Tag;

use App\Enums\TagColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_タグを作成できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('tags.store'), ['name' => '仕事', 'color' => TagColor::Sky->value])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['user_id' => $this->user->id, 'name' => '仕事']);
    }

    public function test_同じ名前のタグは作れない(): void
    {
        Tag::factory()->for($this->user)->create(['name' => '仕事']);

        $this->actingAs($this->user)
            ->post(route('tags.store'), ['name' => '仕事', 'color' => TagColor::Sky->value])
            ->assertSessionHasErrors('name');
    }

    public function test_他のユーザーが同じ名前のタグを持っていても作れる(): void
    {
        Tag::factory()->for(User::factory())->create(['name' => '仕事']);

        $this->actingAs($this->user)
            ->post(route('tags.store'), ['name' => '仕事', 'color' => TagColor::Sky->value])
            ->assertSessionHasNoErrors();
    }

    public function test_他人のタグは更新も削除もできない(): void
    {
        $othersTag = Tag::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->put(route('tags.update', $othersTag), ['name' => '乗っ取り', 'color' => TagColor::Rose->value])
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('tags.destroy', $othersTag))
            ->assertForbidden();
    }

    public function test_タスクにタグを付け外しできる(): void
    {
        $tag = Tag::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->create();

        $payload = [
            'title' => $task->title,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Low->value,
        ];

        $this->actingAs($this->user)->put(route('tasks.update', $task), $payload + ['tags' => [$tag->id]]);
        $this->assertTrue($task->fresh()->tags->contains($tag));

        $this->actingAs($this->user)->put(route('tasks.update', $task), $payload);
        $this->assertTrue($task->fresh()->tags->isEmpty());
    }

    public function test_他人のタグはタスクに付けられない(): void
    {
        $othersTag = Tag::factory()->for(User::factory())->create();

        $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'タスク',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Low->value,
            'tags' => [$othersTag->id],
        ])->assertSessionHasErrors('tags.0');
    }

    public function test_タグで絞り込める(): void
    {
        $tag = Tag::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->create(['title' => 'タグ付き'])->tags()->attach($tag);
        Task::factory()->for($this->user)->create(['title' => 'タグなし']);

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['tag' => $tag->id]))
            ->assertSee('タグ付き')
            ->assertDontSee('タグなし');
    }

    public function test_タグを削除してもタスクは残る(): void
    {
        $tag = Tag::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->create();
        $task->tags()->attach($tag);

        $this->actingAs($this->user)->delete(route('tags.destroy', $tag));

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('tag_task', ['tag_id' => $tag->id]);
    }
}
