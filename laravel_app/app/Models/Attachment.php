<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * 課題の添付ファイル。
 *
 * 中身はストレージに置き、ここには在りかと素性だけを持つ。
 * 行を消したら中身も消す（消し忘れたファイルがストレージに溜まり続けないように）。
 */
class Attachment extends Model
{
    /** @use HasFactory<\Database\Factories\AttachmentFactory> */
    use HasFactory;

    /**
     * 利用者に決めさせる項目は無い。在りかも素性もサーバーが決める
     * （ファイル名だけは利用者のものだが、AttachmentService が整えてから入れる）。
     */
    protected $fillable = [];

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * ブラウザの中で開かせてよいか。画像と PDF だけ。
     * それ以外（テキストを含む）は、中身が何であれダウンロードさせる。
     */
    public function isInlineSafe(): bool
    {
        return in_array($this->mime_type, config('attachments.inline_mime_types'), true);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, maxPrecision: 1);
    }

    public function uploaderName(): string
    {
        return $this->user?->name ?? '削除されたユーザー';
    }
}
