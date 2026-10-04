<?php

namespace Tests\Feature\Issue;

use App\Enums\ActivityField;
use App\Enums\ProjectRole;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 添付ファイル。
 *
 * 上げて見られることに加えて、
 *  - 中身の偽装（拡張子だけ画像の HTML など）を弾く
 *  - 見られない人には「無い」と答える
 *  - 画像と PDF 以外はブラウザの中で開かせない
 * を確かめる。
 */
class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $viewer;

    private User $outsider;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->project = Project::personalFor($this->owner);
        $this->project->members()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);
        $this->project->members()->create(['user_id' => $this->viewer->id, 'role' => ProjectRole::Viewer]);

        $this->issue = Issue::factory()->inProject($this->project, $this->owner)->create();
    }

    private function upload(User $user, UploadedFile $file, array $headers = [])
    {
        return $this->actingAs($user)->post(route('attachments.store', $this->issue), ['file' => $file], $headers);
    }

    public function test_メンバーは添付でき推測できない名前で保存される(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('screen shot.png'))
            ->assertRedirect(route('tasks.show', $this->issue));

        $attachment = Attachment::sole();
        $this->assertSame('screen shot.png', $attachment->original_name);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame($this->member->id, $attachment->user_id);
        $this->assertMatchesRegularExpression(
            "#^{$this->project->id}/{$this->issue->id}/[0-9a-f-]{36}\\.png$#",
            $attachment->path,
        );
        Storage::disk(config('attachments.disk'))->assertExists($attachment->path);
    }

    public function test_追加は履歴に残る(): void
    {
        $this->upload($this->member, UploadedFile::fake()->create('仕様書.pdf', 10, 'application/pdf'));

        $activity = $this->issue->activities()->where('field', ActivityField::Attachment)->sole();
        $this->assertSame('添付ファイル「仕様書.pdf」を追加しました。', $activity->field->describe($activity->old_value, $activity->new_value));
    }

    public function test_fetch_からは_JSON_で相対_URL_を返す(): void
    {
        $response = $this->upload($this->member, UploadedFile::fake()->image('a.png'), ['Accept' => 'application/json'])
            ->assertCreated();

        $attachment = Attachment::sole();
        $response->assertExactJson([
            'id' => $attachment->id,
            'name' => 'a.png',
            'url' => "/attachments/{$attachment->id}",
            'is_image' => true,
        ]);
    }

    public function test_閲覧者と部外者は添付できない(): void
    {
        $this->upload($this->viewer, UploadedFile::fake()->image('a.png'))->assertForbidden();
        $this->upload($this->outsider, UploadedFile::fake()->image('a.png'))->assertNotFound();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_許可していない種類は弾く(): void
    {
        $this->upload($this->member, UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml'))
            ->assertSessionHasErrors('file');
        $this->upload($this->member, UploadedFile::fake()->create('page.html', 1, 'text/html'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_拡張子だけ画像にした_HTML_は中身で弾く(): void
    {
        // fake() のファイルは拡張子から種類を答えるので、中身の判定を試すには本物のファイルが要る
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, '<html><body><script>alert(1)</script></body></html>');
        $file = new UploadedFile($path, 'cat.png', 'image/png', test: true);

        $this->upload($this->member, $file)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_大きすぎるファイルは弾く(): void
    {
        config(['attachments.max_size' => 100]);

        $this->upload($this->member, UploadedFile::fake()->create('big.pdf', 101, 'application/pdf'))
            ->assertSessionHasErrors('file');
    }

    public function test_1_課題あたりの上限を超えると弾く(): void
    {
        config(['attachments.max_per_issue' => 1]);
        Attachment::factory()->for($this->issue)->create();

        $this->upload($this->member, UploadedFile::fake()->image('a.png'))->assertSessionHasErrors('file');
    }

    public function test_画像はブラウザで開き_nosniff_と_sandbox_が付く(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));
        $attachment = Attachment::sole();

        $response = $this->actingAs($this->viewer)->get(route('attachments.show', $attachment))->assertOk();

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_画像と_PDF_以外は必ずダウンロードさせる(): void
    {
        $this->upload($this->member, UploadedFile::fake()->createWithContent('memo.txt', 'hello'));

        $this->actingAs($this->member)->get(route('attachments.show', Attachment::sole()))
            ->assertOk()
            ->assertDownload('memo.txt');
    }

    public function test_download_を付けると画像もダウンロードになる(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));

        $this->actingAs($this->member)
            ->get(route('attachments.show', ['attachment' => Attachment::sole(), 'download' => 1]))
            ->assertDownload('a.png');
    }

    public function test_部外者には添付が無いと答える(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));

        $this->actingAs($this->outsider)->get(route('attachments.show', Attachment::sole()))->assertNotFound();
    }

    public function test_上げた本人は削除でき中身も消える(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));
        $attachment = Attachment::sole();

        $this->actingAs($this->member)
            ->delete(route('attachments.destroy', [$this->issue, $attachment]))
            ->assertRedirect(route('tasks.show', $this->issue));

        $this->assertModelMissing($attachment);
        Storage::disk(config('attachments.disk'))->assertMissing($attachment->path);
        $this->assertTrue($this->issue->activities()->where('field', ActivityField::Attachment)->where('old_value', 'a.png')->exists());
    }

    public function test_他人の添付は消せないが管理者は消せる(): void
    {
        $this->upload($this->owner, UploadedFile::fake()->image('a.png'));
        $attachment = Attachment::sole();

        $this->actingAs($this->member)->delete(route('attachments.destroy', [$this->issue, $attachment]))->assertForbidden();
        $this->assertModelExists($attachment);

        $this->upload($this->member, UploadedFile::fake()->image('b.png'));
        $mine = Attachment::latest('id')->first();

        $this->actingAs($this->owner)->delete(route('attachments.destroy', [$this->issue, $mine]))->assertRedirect();
        $this->assertModelMissing($mine);
    }

    public function test_別の課題の_URL_に差し替えて消すことはできない(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));
        $other = Issue::factory()->inProject($this->project, $this->owner)->create();

        $this->actingAs($this->owner)
            ->delete(route('attachments.destroy', [$other, Attachment::sole()]))
            ->assertNotFound();
    }

    public function test_課題を完全に消すと中身も片付く(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('a.png'));
        $path = Attachment::sole()->path;

        $this->issue->forceDelete();

        $this->assertDatabaseCount('attachments', 0);
        Storage::disk(config('attachments.disk'))->assertMissing($path);
    }

    public function test_課題画面に添付の一覧と追加フォームが出る(): void
    {
        $this->upload($this->member, UploadedFile::fake()->image('screen.png'));

        $this->actingAs($this->member)->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('screen.png')
            ->assertSee('data-attachment-dropzone', false);

        // 閲覧者には一覧だけ
        $this->actingAs($this->viewer)->get(route('tasks.show', $this->issue))
            ->assertOk()
            ->assertSee('screen.png')
            ->assertDontSee('data-attachment-dropzone', false);
    }

    public function test_ファイル名のパスと制御文字は落とす(): void
    {
        $this->assertSame('passwd', AttachmentService::cleanName('../../etc/passwd'));
        $this->assertSame('a.txt', AttachmentService::cleanName("C:\\Users\\x\\a\0.txt"));
        $this->assertSame('file', AttachmentService::cleanName('..'));
        $this->assertSame(204, mb_strlen(AttachmentService::cleanName(str_repeat('あ', 300).'.pdf')) + 10);
    }

    public function test_本文には自分のサーバーの画像だけを残す(): void
    {
        $this->actingAs($this->member)->patch(route('tasks.content', $this->issue), [
            'content' => '<p><img src="/attachments/1" alt="a.png"><img src="https://tracker.example/p.gif"></p>',
        ]);

        $content = $this->issue->fresh()->content;
        $this->assertStringContainsString('<img src="/attachments/1" alt="a.png"', $content);
        $this->assertStringNotContainsString('tracker.example', $content);
    }
}
