<?php

namespace Tests\Feature\Task;

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_いまの条件に名前を付けて保存し一覧から開ける(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('saved-filters.store'), [
            'name' => '自分の急ぎ',
            'query' => ['q' => 'assignee:me priority:high', 'sort' => 'due_date', 'page' => '3', 'evil' => 'x'],
        ])->assertRedirect(route('tasks.index', ['q' => 'assignee:me priority:high', 'sort' => 'due_date']));

        $filter = SavedFilter::sole();
        // ページ番号や知らない項目は保存しない
        $this->assertSame(['q' => 'assignee:me priority:high', 'sort' => 'due_date'], $filter->query);

        $this->actingAs($user)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('保存した条件')
            ->assertSee('自分の急ぎ');
    }

    public function test_同じ名前は二重に保存できない(): void
    {
        $user = User::factory()->create();
        $user->savedFilters()->create(['name' => '急ぎ', 'query' => ['q' => 'priority:high']]);

        $this->actingAs($user)
            ->post(route('saved-filters.store'), ['name' => '急ぎ', 'query' => ['q' => 'is:open']])
            ->assertSessionHasErrors('name');
    }

    public function test_条件が空なら保存しない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('saved-filters.store'), ['name' => '空', 'query' => ['page' => '2']])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('saved_filters', 0);
    }

    public function test_他人の保存した条件は見えず消せない(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $filter = $owner->savedFilters()->create(['name' => '秘密の条件', 'query' => ['q' => 'is:open']]);

        $this->actingAs($intruder)->get(route('tasks.index'))->assertDontSee('秘密の条件');
        $this->actingAs($intruder)->delete(route('saved-filters.destroy', $filter))->assertNotFound();
        $this->assertModelExists($filter);

        $this->actingAs($owner)->delete(route('saved-filters.destroy', $filter))->assertRedirect();
        $this->assertModelMissing($filter);
    }
}
