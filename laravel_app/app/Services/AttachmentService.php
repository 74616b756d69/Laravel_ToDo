<?php

namespace App\Services;

use App\Enums\ActivityField;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * 添付ファイルの保存と削除。
 *
 * 利用者が決められるのは中身とファイル名だけ。保存先のパスは推測できない名前
 * （UUID）をサーバーで振り、拡張子も送られてきた名前ではなく中身から決める。
 * 「invoice.pdf.php」のような名前で置かせて、何かの拍子に実行される、を防ぐため。
 */
class AttachmentService
{
    public function store(Issue $issue, UploadedFile $file, User $uploader): Attachment
    {
        $disk = config('attachments.disk');
        $extension = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs(
            "{$issue->project_id}/{$issue->id}",
            Str::uuid().".{$extension}",
            ['disk' => $disk],
        );

        try {
            return DB::transaction(function () use ($issue, $file, $uploader, $disk, $path) {
                $attachment = $issue->attachments()->make()->forceFill([
                    'user_id' => $uploader->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => self::cleanName($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                ]);
                $attachment->save();

                $this->record($issue, $uploader, null, $attachment->original_name);

                return $attachment;
            });
        } catch (Throwable $e) {
            // 行を作れなかったなら、置いたファイルは誰にも参照されない。片付けてから投げ直す
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    public function delete(Attachment $attachment, User $actor): void
    {
        DB::transaction(function () use ($attachment, $actor) {
            $attachment->delete();

            $this->record($attachment->issue, $actor, $attachment->original_name, null);
        });
    }

    /**
     * 画面とダウンロード時の名前に使えるよう、ファイル名を整える。
     *
     * パスの区切りと制御文字を落とし、長さを切り詰める。
     * 拡張子は残す（ダウンロードした人が開くときに要る）。
     */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return 'file';
        }

        if (mb_strlen($name) <= 200) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190);

        return $extension === '' ? $stem : "{$stem}.{$extension}";
    }

    private function record(Issue $issue, User $actor, ?string $old, ?string $new): void
    {
        Activity::create([
            'issue_id' => $issue->id,
            'user_id' => $actor->id,
            'field' => ActivityField::Attachment,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
