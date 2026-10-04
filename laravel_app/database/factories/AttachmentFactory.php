<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 行だけを作る。中身のファイルが要るテストは、Storage::fake() のうえで
 * 画面（POST）から上げること。
 *
 * @extends Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'disk' => config('attachments.disk'),
            'path' => 'factory/'.Str::uuid().'.txt',
            'original_name' => 'memo.txt',
            'mime_type' => 'text/plain',
            'size' => 128,
        ];
    }
}
