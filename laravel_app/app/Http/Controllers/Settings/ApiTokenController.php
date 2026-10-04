<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * API の個人アクセストークン。
 *
 * トークンの文字列は発行した直後に 1 度だけ見せる。DB にはハッシュしか残らないので、
 * あとから見せることはできない（失くしたら取り消して発行し直す）。
 */
class ApiTokenController extends Controller
{
    /** 有効期限の選択肢（日数）。null は無期限 */
    public const EXPIRATIONS = ['30' => '30 日', '90' => '90 日', '365' => '1 年', 'never' => '無期限'];

    /** 1 人あたりの上限 */
    private const LIMIT = 10;

    public function index(Request $request): View
    {
        return view('settings.tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'plainToken' => session('plain_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(['read', 'write'])],
            'expires' => ['required', Rule::in(array_keys(self::EXPIRATIONS))],
        ], attributes: ['name' => '名前', 'abilities' => '権限', 'expires' => '有効期限']);

        if ($request->user()->tokens()->count() >= self::LIMIT) {
            return back()->withErrors(['name' => '発行できるトークンは '.self::LIMIT.' 個までです。使っていないものを取り消してください。']);
        }

        $abilities = array_values(array_unique($validated['abilities']));

        // 書けるのに読めないトークンは使い道がないので、write には read を含める
        if (in_array('write', $abilities, true) && ! in_array('read', $abilities, true)) {
            $abilities[] = 'read';
        }

        $token = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $validated['expires'] === 'never' ? null : now()->addDays((int) $validated['expires']),
        );

        return redirect()->route('settings.tokens')
            ->with('plain_token', $token->plainTextToken)
            ->with('status', "トークン「{$validated['name']}」を発行しました。");
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $record = $request->user()->tokens()->findOrFail($token);
        $record->delete();

        return redirect()->route('settings.tokens')->with('status', "トークン「{$record->name}」を取り消しました。");
    }
}
