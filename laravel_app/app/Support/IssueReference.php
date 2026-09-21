<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * 入力文字列から既存の課題を引き当てる。
 *
 * サブタスク欄は「タイトルを書けば新規作成、課題を指せば紐づけ」の
 * 両方を 1 つの入力欄で受けるので、指しているかどうかの判定をここに集める。
 */
class IssueReference
{
    /** 課題キー（PROJ-123）。前後の空白は落とし、大文字小文字は問わない */
    private const KEY_PATTERN = '/\A([A-Za-z]{2,10})-(\d+)\z/';

    /**
     * 詳細画面の URL。/tasks/123 の形だけを見る。
     * 区切り文字に # は使えない（クエリやフラグメントの # と衝突する）。
     */
    private const URL_PATTERN = '~/tasks/(\d+)(?:[/?#].*)?\z~';

    /**
     * 入力が既存課題を指しているように見えるか。
     *
     * 「指しているが見つからない」を「新規作成」と取り違えないための判定。
     * 指している“つもり”なら、解決に失敗しても新規作成には倒さない。
     */
    public static function looksLikeReference(string $input): bool
    {
        $input = trim($input);

        return preg_match(self::KEY_PATTERN, $input) === 1
            || preg_match(self::URL_PATTERN, $input) === 1;
    }

    /**
     * 入力が課題キー（PROJ-123）の形か。
     *
     * ヘッダーの検索窓が「キーなら直行、それ以外はキーワード検索」を
     * 分けるために使う。URL 形式はここには含めない。
     */
    public static function looksLikeKey(string $input): bool
    {
        return preg_match(self::KEY_PATTERN, trim($input)) === 1;
    }

    /**
     * 課題キーから、そのユーザーに見える課題を横断で探す。見つからなければ null。
     *
     * /browse/PROJ-123 のようにプロジェクトが分からない状態で引くための入口。
     * 範囲を visibleTo で絞るので、所属していないプロジェクトのキーは
     * 「存在しない」と同じく null になる（存在の有無も漏らさない）。
     */
    public static function resolveKeyFor(string $input, User $user): ?Issue
    {
        if (preg_match(self::KEY_PATTERN, trim($input), $matches) !== 1) {
            return null;
        }

        // プロジェクトキーは常に大文字で保存されている
        $prefix = Str::upper($matches[1]);

        return Issue::query()
            ->visibleTo($user)
            ->whereHas('project', fn (Builder $query) => $query->where('key', $prefix))
            ->where('issue_number', (int) $matches[2])
            ->with('project')
            ->first();
    }

    /**
     * プロジェクト内から該当する課題を探す。見つからなければ null。
     *
     * 探す範囲を必ずプロジェクトに限るのは、他プロジェクトの課題 ID を
     * 直接指定して引き寄せられないようにするため。
     */
    public static function resolve(string $input, Project $project): ?Issue
    {
        $input = trim($input);

        if (preg_match(self::KEY_PATTERN, $input, $matches) === 1) {
            // キーの接頭辞がこのプロジェクトのものと一致することも確かめる
            if (Str::upper($matches[1]) !== Str::upper($project->key)) {
                return null;
            }

            return $project->issues()->where('issue_number', (int) $matches[2])->first();
        }

        if (preg_match(self::URL_PATTERN, $input, $matches) === 1) {
            return $project->issues()->whereKey((int) $matches[1])->first();
        }

        return null;
    }
}
