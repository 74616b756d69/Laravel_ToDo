<?php

namespace App\Models;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 課題へのコメント。
 *
 * 本文は課題本文とまったく同じ経路でサニタイズする。
 * 複数人が書く場所なので、入口が増えるぶん XSS の危険も増える。
 */
class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 投稿者は利用者に決めさせない。外部キーを mass assignment に
     * 晒しておくと、将来 $request->all() を渡したときに詐称できてしまう。
     */
    protected $fillable = ['body', 'edited_at'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
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

    /**
     * body は HTML として保存する。保存の直前に必ず無害化し、
     * 検索用の平文（body_text）も同時に更新する。
     */
    protected function body(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $html = RichText::sanitize($value);

            return [
                'body' => $html,
                'body_text' => $html === null ? null : RichText::toPlainText($html),
            ];
        });
    }

    /**
     * 投稿後に編集されたか。
     */
    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * 表示用の投稿者名。退会したユーザーのコメントも残る。
     */
    public function authorName(): string
    {
        return $this->user?->name ?? '削除されたユーザー';
    }
}
