<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(Request $request, Issue $issue): AnonymousResourceCollection
    {
        $this->authorize('view', $issue);

        return CommentResource::collection($issue->comments()->with('user')->paginate(50));
    }

    /**
     * 投稿。検証とサニタイズ・メンションの扱いは画面からの投稿と同じ（CommentRequest と Comment モデル）。
     */
    public function store(CommentRequest $request, Issue $issue): JsonResponse
    {
        $comment = $issue->comments()->make($request->validated())
            ->forceFill(['user_id' => $request->user()->id]);
        $comment->save();

        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }
}
