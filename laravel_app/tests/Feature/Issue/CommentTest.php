<?php

namespace Tests\Feature\Issue;

use App\Enums\ProjectRole;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * コメントと、詳細画面のタブ。
 *
 * 本文は課題本文とまったく同じサニタイズ経路を通るので、
 * そこが本当に効いているかもここで押さえる。
 */
class CommentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '書いた人']);
        $this->project = Project::personalFor($this->user);
        $this->issue = Issue::factory()->inProject($this->project, $this->user)->create();
    }

    // --- 投稿 ---------------------------------------------------------------

    public function test_コメントを投稿できる(): void
    {
        $this->actingAs($this->user)
            ->post(route('comments.store', $this->issue), ['body' => '<p>確認しました。</p>'])
            ->assertRedirect(route('tasks.show', ['task' => $this->issue, 'tab' => 'comments']));

        $comment = $this->issue->comments()->sole();

        $this->assertSame('<p>確認しました。</p>', $comment->body);
        $this->assertSame($this->user->id, $comment->user_id);
        $this->assertFalse($comment->wasEdited());
    }

    public function test_危険なhtmlは保存時に除去される(): void
    {
        $this->actingAs($this->user)->post(route('comments.store', $this->issue), [
            'body' => '<p>ふつうの本文</p><script>alert(1)</script><a href="javascript:alert(1)">リンク</a>',
        ]);

        $body = $this->issue->comments()->sole()->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('ふつうの本文', $body);
    }

    public function test_検索用の平文が同期される(): void
    {
        $this->actingAs($this->user)->post(route('comments.store', $this->issue), [
            'body' => '<h2>見出し</h2><p>本文です</p>',
        ]);

        $comment = $this->issue->comments()->sole();

        $this->assertStringNotContainsString('<h2>', $comment->body_text);
        $this->assertStringContainsString('見出し', $comment->body_text);
        $this->assertStringContainsString('本文です', $comment->body_text);
    }

    public function test_空のコメントは投稿できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('comments.store', $this->issue), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $this->issue->comments()->count());
    }

    public function test_タグだけのコメントは投稿できない(): void
    {
        // サニタイズすると中身が消える入力。required だけでは通ってしまう
        $this->actingAs($this->user)
            ->post(route('comments.store', $this->issue), ['body' => '<p></p><br>'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $this->issue->comments()->count());
    }

    public function test_長すぎるコメントは弾かれる(): void
    {
        $this->actingAs($this->user)
            ->post(route('comments.store', $this->issue), ['body' => str_repeat('あ', 5001)])
            ->assertSessionHasErrors('body');
    }

    // --- 編集と削除 -----------------------------------------------------------

    public function test_自分のコメントを編集できる(): void
    {
        $comment = Comment::factory()->for($this->issue)->for($this->user)->create();

        $this->actingAs($this->user)
            ->put(route('comments.update', [$this->issue, $comment]), ['body' => '<p>書き直しました。</p>'])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame('<p>書き直しました。</p>', $comment->body);
        $this->assertTrue($comment->wasEdited());
    }

    public function test_自分のコメントを削除できる(): void
    {
        $comment = Comment::factory()->for($this->issue)->for($this->user)->create();

        $this->actingAs($this->user)
            ->delete(route('comments.destroy', [$this->issue, $comment]))
            ->assertRedirect();

        $this->assertSoftDeleted($comment);
    }

    /**
     * 管理者でも他人の発言は書き換えさせない。
     * 議論の記録が信用できなくなるため。
     */
    public function test_他人のコメントは管理者でも編集できない(): void
    {
        $colleague = User::factory()->create();
        $this->project->members()->create(['user_id' => $colleague->id, 'role' => ProjectRole::Member]);

        $comment = Comment::factory()->for($this->issue)->for($colleague)->create();

        // $this->user はこのプロジェクトの admin
        $this->actingAs($this->user)
            ->put(route('comments.update', [$this->issue, $comment]), ['body' => '<p>改ざん</p>'])
            ->assertForbidden();
    }

    public function test_他人のコメントも管理者なら削除できる(): void
    {
        $colleague = User::factory()->create();
        $this->project->members()->create(['user_id' => $colleague->id, 'role' => ProjectRole::Member]);

        $comment = Comment::factory()->for($this->issue)->for($colleague)->create();

        $this->actingAs($this->user)
            ->delete(route('comments.destroy', [$this->issue, $comment]))
            ->assertRedirect();

        $this->assertSoftDeleted($comment);
    }

    public function test_別の課題のコメントは操作できない(): void
    {
        $other = Issue::factory()->inProject($this->project, $this->user)->create();
        $comment = Comment::factory()->for($other)->for($this->user)->create();

        // URL の課題とコメントの組み合わせが食い違うケース
        $this->actingAs($this->user)
            ->delete(route('comments.destroy', [$this->issue, $comment]))
            ->assertNotFound();
    }

    public function test_課題を完全に削除するとコメントも消える(): void
    {
        Comment::factory()->count(2)->for($this->issue)->for($this->user)->create();

        $this->issue->forceDelete();

        $this->assertSame(0, Comment::withTrashed()->where('issue_id', $this->issue->id)->count());
    }

    // --- 権限 ---------------------------------------------------------------

    public function test_閲覧者はコメントできない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $issue = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->post(route('comments.store', $issue), ['body' => '<p>閲覧者の発言</p>'])
            ->assertForbidden();
    }

    public function test_参加していないプロジェクトの課題にはコメントできない(): void
    {
        $others = Issue::factory()->create();

        $this->actingAs($this->user)
            ->post(route('comments.store', $others), ['body' => '<p>割り込み</p>'])
            ->assertForbidden();
    }

    // --- タブ ---------------------------------------------------------------

    public function test_すべてタブにコメントと履歴が時系列で並ぶ(): void
    {
        $this->actingAs($this->user);

        // 作成の履歴 → コメント → ステータス変更の履歴、の順になるようにする
        $this->travel(1)->minutes();
        Comment::factory()->for($this->issue)->for($this->user)->create(['body' => '<p>先に書いたコメント</p>']);

        $this->travel(1)->minutes();
        $this->issue->update(['story_points' => 5]);

        $timeline = $this->get(route('tasks.show', $this->issue))->assertOk()->viewData('timeline');

        $this->assertSame(
            ['activity', 'comment', 'activity'],
            $timeline->pluck('kind')->all(),
        );
    }

    public function test_コメントタブにはコメントだけが出る(): void
    {
        $this->actingAs($this->user);
        Comment::factory()->for($this->issue)->for($this->user)->create();
        $this->issue->update(['story_points' => 3]);

        $timeline = $this->get(route('tasks.show', ['task' => $this->issue, 'tab' => 'comments']))
            ->assertOk()
            ->viewData('timeline');

        $this->assertSame(['comment'], $timeline->pluck('kind')->unique()->all());
    }

    public function test_履歴タブには履歴だけが出る(): void
    {
        $this->actingAs($this->user);
        Comment::factory()->for($this->issue)->for($this->user)->create(['body' => '<p>出ないはずのコメント</p>']);
        $this->issue->update(['story_points' => 3]);

        $response = $this->get(route('tasks.show', ['task' => $this->issue, 'tab' => 'history']))->assertOk();

        $this->assertSame(['activity'], $response->viewData('timeline')->pluck('kind')->unique()->all());
        $response->assertDontSee('出ないはずのコメント');
    }

    public function test_不正なタブ名はすべてとして扱う(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('tasks.show', ['task' => $this->issue, 'tab' => 'unknown']))
            ->assertOk();

        $this->assertSame('all', $response->viewData('tab'));
    }

    public function test_履歴タブにはコメント投稿フォームを出さない(): void
    {
        $this->actingAs($this->user)
            ->get(route('tasks.show', ['task' => $this->issue, 'tab' => 'history']))
            ->assertOk()
            ->assertDontSee('コメントする');
    }

    public function test_閲覧者には投稿フォームが出ない(): void
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();
        $issue = Issue::factory()->inProject($project)->create();

        $this->actingAs($viewer)
            ->get(route('tasks.show', $issue))
            ->assertOk()
            ->assertDontSee('コメントする');
    }

    /**
     * 回帰: コメントが 2 件以上あると詳細画面が 500 になっていた。
     *
     * CommentPolicy が $comment->issue を遅延ロードしており、
     * strict モードで例外になっていた（1 件だけだと自動ロードに救われて素通りする）。
     */
    public function test_コメントが複数あっても詳細画面が開く(): void
    {
        $colleague = User::factory()->create();
        $this->project->members()->create(['user_id' => $colleague->id, 'role' => ProjectRole::Member]);

        Comment::factory()->count(3)->for($this->issue)->for($this->user)->create();
        Comment::factory()->count(3)->for($this->issue)->for($colleague)->create();

        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->issue))
            ->assertOk();
    }

    /**
     * 回帰: コメント数に比例してクエリが増えていた。
     */
    public function test_コメントを増やしてもクエリ数が増えない(): void
    {
        $colleague = User::factory()->create();
        $this->project->members()->create(['user_id' => $colleague->id, 'role' => ProjectRole::Member]);

        $measure = function (): int {
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });
            $this->actingAs($this->user)->get(route('tasks.show', $this->issue))->assertOk();

            return $count;
        };

        Comment::factory()->count(2)->for($this->issue)->for($colleague)->create();
        $few = $measure();

        Comment::factory()->count(20)->for($this->issue)->for($colleague)->create();
        $many = $measure();

        $this->assertSame($few, $many, "コメントを増やすとクエリが {$few} → {$many} に増えています");
    }

    /**
     * 一般メンバーは、他人のコメントを消せない。
     *
     * 「管理者なら消せる」だけを書いていたので、CommentPolicy::delete を
     * 常に true にしても気づけない状態だった。
     */
    public function test_一般メンバーは他人のコメントを削除できない(): void
    {
        $member = User::factory()->create();
        $author = User::factory()->create();
        $this->project->members()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);
        $this->project->members()->create(['user_id' => $author->id, 'role' => ProjectRole::Member]);

        $comment = Comment::factory()->for($this->issue)->for($author)->create();

        $this->actingAs($member)
            ->delete(route('comments.destroy', [$this->issue, $comment]))
            ->assertForbidden();

        $this->assertNotSoftDeleted($comment);
    }

    public function test_閲覧者は他人のコメントを削除できない(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $comment = Comment::factory()->for($this->issue)->for($this->user)->create();

        $this->actingAs($viewer)
            ->delete(route('comments.destroy', [$this->issue, $comment]))
            ->assertForbidden();
    }

    // --- 認可が検証より先に走ること -------------------------------------------

    /**
     * 権限の無い相手には、検証結果ではなく 403 を返す。
     * 逆順だと、触れない課題の事情が検証エラー越しに漏れる。
     */
    public function test_権限が無ければ検証より先に403を返す(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('comments.store', $this->issue), ['body' => ''])
            ->assertForbidden();

        $viewer = User::factory()->create();
        $this->project->members()->create(['user_id' => $viewer->id, 'role' => ProjectRole::Viewer]);

        $this->actingAs($viewer)
            ->post(route('comments.store', $this->issue), ['body' => ''])
            ->assertForbidden();
    }

    public function test_削除したコメントは一覧に出ない(): void
    {
        $comment = Comment::factory()->for($this->issue)->for($this->user)
            ->create(['body' => '<p>消したコメント</p>']);

        $comment->delete();

        $this->actingAs($this->user)
            ->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertDontSee('消したコメント');
    }
}
