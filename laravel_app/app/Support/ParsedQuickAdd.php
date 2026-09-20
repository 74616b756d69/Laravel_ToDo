<?php

namespace App\Support;

use App\Enums\TaskPriority;
use Illuminate\Support\Carbon;

/**
 * クイック追加の解析結果。
 */
final readonly class ParsedQuickAdd
{
    /**
     * @param  array<int, int>  $tagIds
     * @param  array<int, string>  $unknownTags  一致するタグが無かった指定（画面での注意喚起に使う）
     */
    public function __construct(
        public string $title,
        public TaskPriority $priority,
        public ?Carbon $dueDate,
        public array $tagIds,
        public array $unknownTags,
    ) {}

    public function hasTitle(): bool
    {
        return $this->title !== '';
    }
}
