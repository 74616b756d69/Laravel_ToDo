<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

class User extends Model
{
    function profile() {
        return $this->hasOne(profile::class);
    }

    /*`コントローラーでの使用例①
        1.find(1)でidか1のUserを絞り込み
        2.そのユーザーに紐付いてるProfikeのみを取得
        $profile = User::find(1)->profile;
    */

    function tasks() {
        return $this->hasMany(Task::class);
    }

    /* コントローラーでの使用例②
        // 1. find(1)でidが1のUserを絞り込み
        // 2. そのユーザーに紐付いているTaskのみを取得
        $tasks = User::find(1)->tasks;
    */
}
