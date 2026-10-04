<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 保存した絞り込み条件。持ち主しか見えない・使えない。
 */
class SavedFilter extends Model
{
    /**
     * 一覧の URL に載せてよい項目。これ以外は保存しない
     * （page を保存すると、開くたびに途中のページから始まってしまう）。
     */
    public const KEYS = ['q', 'keyword', 'project', 'status', 'category', 'priority', 'tag', 'overdue', 'sort'];

    protected $fillable = ['name', 'query'];

    protected function casts(): array
    {
        return ['query' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 一覧の URL のクエリから、保存してよい項目だけを取り出す（空の値も落とす）。
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function extract(array $query): array
    {
        return collect($query)
            ->only(self::KEYS)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->all();
    }

    public function url(): string
    {
        return route('tasks.index', $this->query);
    }
}
