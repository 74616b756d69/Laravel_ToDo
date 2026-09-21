<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * 「いま見ているプロジェクト」。
 *
 * ボード・バックログ・課題の作成先は、どれも 1 つのプロジェクトを前提にしている。
 * その 1 つをどこから決めるかを、この 1 箇所に閉じ込める。
 *
 * セッションに入っているのは「利用者が最後に選んだ ID」でしかないので、
 * 値そのものは信用しない。毎回 visibleTo で所属を引き直し、
 * 外れていたら既定へ落とす（招待を外されたあとも画面が開けるように）。
 */
class ProjectContext
{
    /** 切り替えた先を覚えておくセッションキー */
    public const SESSION_KEY = 'current_project_id';

    /**
     * いま見ているプロジェクト。
     *
     * 結果を持ち回らないのは、このクラスが「セッションの今の値」を映す鏡だから。
     * 覚え込ませると、切り替えた直後や次のリクエストで古い値を返しうる。
     * 1 回あたりのクエリは主キー引き 1 本なので、都度引いて構わない。
     */
    public function current(User $user): Project
    {
        return $this->resolve($user);
    }

    /**
     * 切り替える。所属しているかの判定はここではなく呼び出し側の Policy が行う
     * （「見えない ID を指された」ときに 403 を返せるのは認可だけなので）。
     */
    public function switchTo(Project $project): void
    {
        Session::put(self::SESSION_KEY, $project->id);
    }

    /**
     * 切り替え先に出せるプロジェクト。
     *
     * @return Collection<int, Project>
     */
    public function available(User $user): Collection
    {
        return Project::query()->visibleTo($user)->orderBy('name')->get();
    }

    /**
     * 既定は「いちばん古い所属プロジェクト」。
     * どこにも居ない人には個人プロジェクトを作って渡す（従来の挙動）。
     */
    private function resolve(User $user): Project
    {
        $id = Session::get(self::SESSION_KEY);

        if ($id !== null) {
            $project = Project::query()->visibleTo($user)->find($id);

            if ($project !== null) {
                return $project;
            }

            // 所属から外れた・消えた ID を握り続けない
            Session::forget(self::SESSION_KEY);
        }

        return Project::query()->visibleTo($user)->oldest('id')->first()
            ?? Project::personalFor($user);
    }
}
