<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * 参加しているプロジェクト。役割（admin / member / viewer）つき。
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($request->user()->projects()->get());
    }

    /**
     * プロジェクト 1 件。課題を作るときに要るステータスの一覧も返す。
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        // 参加していないプロジェクトは「無い」と答える（キーの存在を漏らさない）
        abort_unless($request->user()->can('view', $project), 404);

        return new ProjectResource($project->load('statuses'));
    }
}
