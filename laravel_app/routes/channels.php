<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * リアルタイム更新のチャンネル。プロジェクトのメンバーだけが購読できる。
 * 判定の根拠は画面の認可と同じ（project_members）。
 */
Broadcast::channel('projects.{project}', fn (User $user, Project $project) => $project->hasMember($user));
