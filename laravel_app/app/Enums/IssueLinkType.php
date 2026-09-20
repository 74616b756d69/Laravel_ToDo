<?php

namespace App\Enums;

/**
 * 課題どうしの関連。
 *
 * 親子（サブタスク）とは別物で、こちらは相手を一覧から消さない。
 * 「関係がある」とだけ言いたいときに使う。
 *
 * 向きのある種別は、見る側によって読み方が変わる。
 * 例: A が B をブロックしているとき、B から見れば「A にブロックされている」。
 */
enum IssueLinkType: string
{
    case Relates = 'relates';
    case Blocks = 'blocks';
    case Duplicates = 'duplicates';

    /**
     * 張った側から見た読み方。
     */
    public function outwardLabel(): string
    {
        return match ($this) {
            self::Relates => 'が関連',
            self::Blocks => 'をブロック',
            self::Duplicates => 'が重複している',
        };
    }

    /**
     * 張られた側から見た読み方。
     */
    public function inwardLabel(): string
    {
        return match ($this) {
            self::Relates => 'が関連',
            self::Blocks => 'にブロックされている',
            self::Duplicates => 'が重複されている',
        };
    }

    /**
     * 向きの有無。関連は対称なので、どちらから見ても同じ。
     */
    public function isSymmetric(): bool
    {
        return $this === self::Relates;
    }

    /**
     * 選択肢。張る側の読み方で出す。
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->outwardLabel()])
            ->all();
    }
}
