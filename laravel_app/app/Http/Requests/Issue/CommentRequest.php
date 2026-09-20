<?php

namespace App\Http\Requests\Issue;

use App\Models\Comment;
use App\Support\RichText;
use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    /**
     * 認可を検証より先に済ませる。
     *
     * 逆順だと、触れない相手にまで検証結果が返ってしまう。
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return $comment === null
            ? $this->user()->can('create', [Comment::class, $this->route('task')])
            : $this->user()->can('update', $comment);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['body' => 'コメント'];
    }

    /**
     * サニタイズすると中身が消える入力（タグだけ、空白だけ）を弾く。
     *
     * required だけだと「<p></p>」のような空コメントが通ってしまう。
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->has('body')) {
                    return;
                }

                if (RichText::sanitize($this->input('body')) === null) {
                    $validator->errors()->add('body', 'コメントを入力してください。');
                }
            },
        ];
    }
}
