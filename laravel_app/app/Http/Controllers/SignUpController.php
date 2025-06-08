<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; //Userモデルを使用する
use Illuminate\Support\Facades\Hash; // パスワードのハッシュ化

class SignUpController extends Controller
{
    // エラーを受け取るためにRequestを追加
    function index(Request $request) {
        $error = $request["error"];

        return view("sign_up.index", compact("error"));
    }

    function store(Request $request) {
        // それぞれの入力値を取得
        $name = $request["name"];
        $email = $request["email"];
        $password = $request("password");
        $passwordConfirmation = $request["passwordConfirmation"];

        // パスワードが一致するかどうか
        if ($password !== $passwordConfirmation) {
            $error = "パスワードが一致しません";
            return view("sign_up.index", compact("error"));
        }

        // where('カラム名', 値)絞り込み
        // exists() データが存在するかどうかtrue or falseで返す
        // メールアドレスが即に登録されているかどうか
        if (User::where('email', $email)->exists()) {
            $error = "既に登録されているメールアドレスです";
            return view("sign_up.index", compact("error"));
        }

        // create(連想配列) データを追加する
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password), //パスワードをハッシュ化して保存
        ]);

        // auth()->login($user) ログイン処理
        auth()->login($user);

        return redirect()->route('task');
    }
}
