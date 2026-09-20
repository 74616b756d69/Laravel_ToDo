<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tag\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(): View
    {
        // タグごとの利用件数を 1 クエリで添える
        $tags = Auth::user()->tags()->withCount('tasks')->get();

        return view('tags.index', compact('tags'));
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $tag = Auth::user()->tags()->create($request->validated());

        return back()->with('status', "タグ「{$tag->name}」を作成しました。");
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());

        return back()->with('status', "タグ「{$tag->name}」を更新しました。");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return back()->with('status', "タグ「{$tag->name}」を削除しました。");
    }
}
