<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 一覧の絞り込み条件を名前を付けて保存する。
 *
 * 個人のものなので Policy は置かず、自分の行だけを引く（他人の id は 404）。
 */
class SavedFilterController extends Controller
{
    /** 1 人あたりの上限。一覧の上に並べるので、増えすぎると探せなくなる */
    private const LIMIT = 20;

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('saved_filters')->where('user_id', $user->id)],
            'query' => ['required', 'array'],
        ], attributes: ['name' => '名前', 'query' => '条件']);

        $query = SavedFilter::extract($validated['query']);

        if ($query === []) {
            return back()->withErrors(['name' => '絞り込み条件がありません。条件を指定してから保存してください。']);
        }

        if ($user->savedFilters()->count() >= self::LIMIT) {
            return back()->withErrors(['name' => '保存できる条件は '.self::LIMIT.' 件までです。']);
        }

        $filter = $user->savedFilters()->create(['name' => $validated['name'], 'query' => $query]);

        return redirect($filter->url())->with('status', "条件「{$filter->name}」を保存しました。");
    }

    public function destroy(Request $request, int $savedFilter): RedirectResponse
    {
        $filter = $request->user()->savedFilters()->findOrFail($savedFilter);
        $filter->delete();

        return back()->with('status', "条件「{$filter->name}」を削除しました。");
    }
}
