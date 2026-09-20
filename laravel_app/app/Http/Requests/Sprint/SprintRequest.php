<?php

namespace App\Http\Requests\Sprint;

use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SprintRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     *
     * 逆順だと、他プロジェクトのスプリント名が存在するかどうかを
     * unique エラーの有無から推測できてしまう。
     */
    public function authorize(): bool
    {
        $sprint = $this->route('sprint');

        return $sprint === null
            ? $this->user()->can('create', [Sprint::class, $this->project()])
            : $this->user()->can('update', $sprint);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                // 同じプロジェクト内で名前は重複させない
                Rule::unique('sprints', 'name')
                    ->where('project_id', $this->project()->id)
                    ->ignore($this->route('sprint')),
            ],
            'goal' => ['nullable', 'string', 'max:500'],
            'start_date' => ['nullable', 'date'],
            // 終了日は開始日以降。片方だけの指定も許す
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'スプリント名',
            'goal' => 'ゴール',
            'start_date' => '開始日',
            'end_date' => '終了日',
        ];
    }

    public function project(): Project
    {
        return $this->route('sprint')?->project ?? Project::personalFor($this->user());
    }
}
