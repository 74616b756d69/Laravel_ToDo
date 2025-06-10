<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; //ログイン情報取得に使用

class TaskController extends Controller
{
    //
    function index() {
        // Atuh::user()でログインしているユーザーの情報を取得
        $user = Auth::user();

        // tasksリレーションを」使用してユーザーに紐づくタスクを取得
        $tasks = $user->tasks;
        return view("task.index", compact("tasks"));
    }

    // createメソットを追加
    function create() {
        return view("task.create");
    }

    // storeメソッドを追加
    function store(Request $request) {
        // それぞれの入力値を取得
        $title = $request["title"];
        $content = $request["content"];

        // Atuh::user()でログインしているユーザーの情報を取得
        $user = Auth::user();

        // tasksリレーションを使用してユーザーに紐づくタスクを作成
        $user->tasks()->create([
            "title" => $title,
            "content" => $content
        ]);

        //タスク一覧画面にリダイレクト
        return redirect()->route("task");
    }

    // ルーティングの{id}は第一引数に入る
    function show($id) {
        // Atuh::user()でログインしているユーザーの情報を取得
        $user = Auth::user();

        //tasksリレーションを使用してユーザーに紐づくタスクを取得
        // $idとtasksテーブルのidが一致するものを取得
        $task = $user->tasks->find($id);

        return view("task.show", compact("task"));
    }

    //editメソッドを追加
    function edit($id) {
        //Atuh::user()ログインしているユーザ-の情報を取得
        $user = Auth::user();
        
        // tasksエイレーションを使用してユーザーに紐づくタスクを取得
        // $idとtasksテーブルのidが一致するものを取得
        $task = $user->tasks->find($id);

        return view("task.edit", compact("task"));
    }

    //updateメソッドを追加
    function update(Request $request, $id) {
        // それぞれの入力値を取得
        $title = $request["title"];
        $content = $request["content"];

        // Auth::user()でログインしているユーザーの情報を取得
        $user = Auth::user();

        // tasksリレーションを使用してユーザーに紐づくタスクを取得
        // $idとtasksテーブルのidが一致するものを取得
        // updateメソッドで更新
        $user->tasks()->find($id)->update([
            "title" => $title,
            "content" => $content
        ]);

        return redirect()->route("task");
    }

    // destroyメソッドを追加
    function destroy($id) {
        // Atuh::user()でログインしているユーザーの情報を取得
        $user = Auth::user();

        // tasksリレーションを使用してユーザーに紐づくタスクを取得
        // $idとtasksテーブルのidが一致するものを取得
        // deleteメソッドで削除
        $user->tasks()->find($id)->delete();

        return redirect()->route("task");
    }
}
