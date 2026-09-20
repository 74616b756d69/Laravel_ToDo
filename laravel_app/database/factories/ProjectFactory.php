<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // 課題キーは大文字英字 2〜10 桁。lexify は小文字を返すので大文字に直す
            'key' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->unique()->lexify('プロジェクト???'),
            // realText() は日本語コーパスを丸ごと読み込んで重いので使わない
            // （IssueFactory と同じ理由。テスト全体のメモリを食い潰す）
            'description' => fake()->optional()->randomElement([
                '社内向けの改善をまとめて進める。',
                '次のリリースに向けた準備。',
                '技術的負債の返済と運用改善。',
            ]),
        ];
    }

    /**
     * 指定ユーザーをメンバーとして参加させた状態。
     */
    public function withMember(User $user, ProjectRole $role = ProjectRole::Admin): static
    {
        return $this->afterCreating(fn (Project $project) => $project->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]));
    }
}
