<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\IssueImport;
use App\Services\IssueImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * CSV の取り込みをキューで行う。1000 行あってもリクエストを待たせない。
 *
 * 再試行はしない。全部か無しかで取り込むので、失敗したら結果を見て直してもらう。
 */
class ImportIssues implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly IssueImport $import,
    ) {}

    public function handle(IssueImporter $importer): void
    {
        $importer->run($this->import);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->forceFill([
            'status' => ImportStatus::Failed,
            'errors' => [['row' => 0, 'message' => '取り込み中に予期しないエラーが起きました。時間をおいてやり直してください。']],
            'finished_at' => now(),
        ])->save();
    }
}
