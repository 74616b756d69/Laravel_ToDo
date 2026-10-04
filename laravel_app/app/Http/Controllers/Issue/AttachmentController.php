<?php

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\AttachmentRequest;
use App\Models\Attachment;
use App\Models\Issue;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 課題の添付ファイル。
 *
 * ファイルは公開ディレクトリに置かず、読み出しは必ずここを通す。
 * URL を知っているだけでは開けず、課題を見られる人にしか返さない。
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * アップロード。フォームからでも、エディタへの貼り付け（fetch）からでも受ける。
     */
    public function store(AttachmentRequest $request, Issue $task): RedirectResponse|JsonResponse
    {
        $attachment = $this->attachments->store($task, $request->file('file'), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                // 相対 URL で返す。本文に埋めた画像が、ドメインやポートの違いで外部扱いされないように
                'url' => route('attachments.show', $attachment, absolute: false),
                'is_image' => $attachment->isImage(),
            ], 201);
        }

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', "「{$attachment->original_name}」を添付しました。");
    }

    /**
     * 中身を返す。
     *
     * 画像と PDF だけはブラウザの中で開かせ、それ以外は必ずダウンロードにする。
     * 種類は保存時に中身から判定した値を使い、nosniff でブラウザの推測も止める。
     * さらに sandbox の CSP を付けて、万一開かれてもスクリプトは動かないようにする。
     */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        // 見られない人には「無い」と答える。403 だと、添付の有無が漏れる
        abort_unless($request->user()->can('view', $attachment), 404);

        $inline = $attachment->isInlineSafe() && ! $request->boolean('download');

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
                'Cache-Control' => 'private, max-age=3600',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    public function destroy(Request $request, Issue $task, Attachment $attachment): RedirectResponse
    {
        // URL の組み合わせを差し替えて、他の課題の添付を消されないようにする
        abort_unless($attachment->issue_id === $task->id, 404);
        $this->authorize('delete', $attachment);

        $this->attachments->delete($attachment, $request->user());

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', "「{$attachment->original_name}」を削除しました。");
    }
}
