<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * デモアカウントでのワンクリックログイン。
 *
 * 閲覧者がアカウントを作らずに中身を確認できるようにするための入口。
 * 対象は config/demo.php で指定した 1 アカウントだけに限定し、
 * 設定で無効化されていれば存在しないルートとして扱う。
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        $user = User::where('email', config('demo.email'))->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'デモアカウントが未作成です。`php artisan db:seed` を実行してください。',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // デモは分析画面から見せたほうが、何ができるアプリか伝わりやすい
        return redirect()->route('dashboard')
            ->with('status', 'デモアカウントでログインしました。自由に操作して構いません。');
    }
}
