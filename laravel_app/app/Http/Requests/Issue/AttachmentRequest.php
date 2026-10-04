<?php

namespace App\Http\Requests\Issue;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;

class AttachmentRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる（CommentRequest と同じ理由）。
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attachment::class, $this->route('task')]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $extensions = implode(',', config('attachments.extensions'));

        return [
            'file' => [
                'required',
                'file',
                'max:'.config('attachments.max_size'),
                // extensions は名前、mimes は中身。両方を満たすものだけを通す
                "extensions:{$extensions}",
                "mimes:{$extensions}",
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file' => 'ファイル'];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $limit = config('attachments.max_per_issue');

                if ($this->route('task')->attachments()->count() >= $limit) {
                    $validator->errors()->add('file', "添付できるのは 1 課題あたり {$limit} 件までです。");
                }
            },
        ];
    }
}
