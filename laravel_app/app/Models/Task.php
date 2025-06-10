<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    // カラムを配列で指定する
    protected $fillable = ['user_id', 'title', 'content'];

    //以下のように
    //Task；；処理１()->処理２（）->処理3()...;
    //taskテーブルからuser_idが1のものを取得できる。
    // $task = Task:: where('user_id', 1)->get();

    //where()は絞り込み
    //get()は取得
}
