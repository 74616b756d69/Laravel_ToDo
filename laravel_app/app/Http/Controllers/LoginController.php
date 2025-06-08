<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; //Userモデルを使用
use Illuminate\Support\Facades\Hash; // ハッシュパスワードのチェックに使用

class LoginController extends Controller
{
    // エラーを受け取るためにRequestを追加
    function index(Request $requrst) {
        $error = $requrst["error"];

        return view("login.index", compact("error"));
    }

    function store(Request $requrst) {
        //　それぞれの入力値を取得
        $email = $request["email"];
        $password = $request["password"];

        //emailで絞り込み
        $user =User::where('email', $email)->first();

        //ユーザーが存在しない場合 or パスワードが一致しない場合にエラーを返す
        // Hash::check()でパスワードのチェックを行える
        if (!$user || !Hash::check($password, $user->password)) {
            $error = "メールアドレスまたはパスワードが間違っています";
            return view('login.index', compact("error"));
        }

        // auth()->login($user)　ログイン処理
        auth()->login($user);

        return redirect()->route("task");
    }
}
