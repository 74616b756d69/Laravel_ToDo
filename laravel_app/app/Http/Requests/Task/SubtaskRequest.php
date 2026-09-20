<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

/**
 * サブタスク欄の 1 行。
 *
 * タイトルとしても、既存課題への参照（PROJ-12 / 詳細画面の URL）としても
 * 受け取る。どちらとして扱うかは IssueHierarchyService が決める。
 */
class SubtaskRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // URL を貼れるようにしたぶん、タイトルより長い入力を許す
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'サブタスク'];
    }
}
