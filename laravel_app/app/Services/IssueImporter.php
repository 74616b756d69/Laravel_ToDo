<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\IssueType;
use App\Enums\TagColor;
use App\Enums\TaskPriority;
use App\Models\IssueImport;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use App\Support\Csv\IssueCsv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplTempFileObject;

/**
 * CSV から課題をまとめて作る。
 *
 * 全行を先に確かめ、1 行でもだめなら 1 件も作らない（全部か無しか）。
 * 途中まで取り込まれると、直して読み込み直したときに前半が二重にできてしまうため。
 *
 * 取り込みで作った課題には操作者を付けない。
 * 何百件ぶんの「担当になりました」通知や Webhook が一度に飛ばないようにするため。
 * キューのワーカーには元々ログイン中の人がいないが、sync キュー（テストや手元）では
 * リクエストの利用者が見えてしまうので、取り込みの間だけ明示的に外す。
 */
class IssueImporter
{
    /** 1 回に取り込める行数 */
    public const MAX_ROWS = 1000;

    /** 画面に出すエラーの件数の上限 */
    private const MAX_ERRORS = 100;

    public function run(IssueImport $import): void
    {
        $import->forceFill(['status' => ImportStatus::Processing])->save();

        try {
            $project = $import->project;
            $user = $import->user;

            [$rows, $errors] = $this->read(Storage::disk('local')->get($import->path) ?? '');

            if ($errors === []) {
                [$prepared, $errors] = $this->prepare($rows, $project);
            }

            if ($errors !== []) {
                $this->finish($import, ImportStatus::Failed, count($rows ?? []), 0, $errors);

                return;
            }

            $this->withoutActor(fn () => DB::transaction(function () use ($prepared, $project, $user) {
                foreach ($prepared as $row) {
                    $issue = $project->createIssue([
                        ...$row['attributes'],
                        'reporter_id' => $user->id,
                    ]);

                    if ($row['tags'] !== []) {
                        $issue->tags()->sync($this->tagIds($user, $row['tags']));
                    }
                }
            }));

            $this->finish($import, ImportStatus::Completed, count($prepared), count($prepared), []);
        } finally {
            // 取り込みが済めば元のファイルは要らない（課題の中身を含むので残さない）
            Storage::disk('local')->delete($import->path);
        }
    }

    /**
     * CSV を読んで、見出しをキーにした行の配列にする。
     *
     * Excel で保存した Shift_JIS の CSV も読めるよう、UTF-8 でなければ変換する。
     *
     * @return array{0: list<array{line: int, values: array<string, string>}>, 1: list<array{row: int, message: string}>}
     */
    private function read(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? '';

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'SJIS-win');
        }

        $file = new SplTempFileObject;
        $file->fwrite($contents);
        $file->rewind();
        $file->setFlags(SplTempFileObject::READ_CSV | SplTempFileObject::SKIP_EMPTY | SplTempFileObject::READ_AHEAD);
        $file->setCsvControl(escape: '');

        $header = null;
        $rows = [];

        foreach ($file as $index => $values) {
            if (! is_array($values) || $values === [null]) {
                continue;
            }

            $values = array_map(fn ($value) => trim((string) $value), $values);

            if ($header === null) {
                $header = $values;

                if (! in_array('タイトル', $header, true)) {
                    return [[], [['row' => 1, 'message' => '見出しの行に「タイトル」の列がありません。テンプレートを使ってください。']]];
                }

                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                return [$rows, [['row' => $index + 1, 'message' => '一度に取り込めるのは '.self::MAX_ROWS.' 行までです。ファイルを分けてください。']]];
            }

            $assoc = [];

            foreach ($header as $position => $name) {
                if (in_array($name, IssueCsv::IMPORTABLE, true)) {
                    $assoc[$name] = IssueCsv::uncell($values[$position] ?? '');
                }
            }

            $rows[] = ['line' => $index + 1, 'values' => $assoc];
        }

        if ($header === null || $rows === []) {
            return [[], [['row' => 1, 'message' => '取り込む行がありません。']]];
        }

        return [$rows, []];
    }

    /**
     * 各行を確かめ、課題の属性にする。
     *
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return array{0: list<array{attributes: array<string, mixed>, tags: list<string>}>, 1: list<array{row: int, message: string}>}
     */
    private function prepare(array $rows, Project $project): array
    {
        /** @var Collection<string, Status> $statuses */
        $statuses = $project->statuses()->get()->keyBy('name');
        $initial = $project->initialStatus();
        $members = $project->users()->get()->keyBy(fn (User $user) => mb_strtolower($user->email));

        $prepared = [];
        $errors = [];

        foreach ($rows as ['line' => $line, 'values' => $values]) {
            $fail = function (string $message) use (&$errors, $line) {
                if (count($errors) < self::MAX_ERRORS) {
                    $errors[] = ['row' => $line, 'message' => $message];
                }
            };

            $title = $values['タイトル'] ?? '';
            $typeText = $values['タイプ'] ?? '';
            $statusText = $values['ステータス'] ?? '';
            $priorityText = $values['優先度'] ?? '';
            $email = mb_strtolower($values['担当者メール'] ?? '');
            $points = $values['ストーリーポイント'] ?? '';
            $estimate = $values['見積もり（分）'] ?? '';
            $due = $values['期限'] ?? '';

            $type = $typeText === '' ? IssueType::Task : self::enumByLabel(IssueType::class, $typeText);
            $status = $statusText === '' ? $initial : $statuses->get($statusText);
            $priority = $priorityText === '' ? TaskPriority::Medium : self::enumByLabel(TaskPriority::class, $priorityText);
            $assignee = $email === '' ? null : $members->get($email);
            $minutes = IssueCsv::minutes($estimate);
            $dueDate = $due === '' ? null : self::date($due);

            $before = count($errors);

            match (true) {
                $title === '' => $fail('タイトルが空です。'),
                mb_strlen($title) > 255 => $fail('タイトルは 255 文字以内にしてください。'),
                default => null,
            };

            if ($type === null) {
                $fail("タイプ「{$typeText}」はありません（エピック / ストーリー / タスク / バグ）。");
            } elseif ($type === IssueType::Subtask) {
                $fail('サブタスクは取り込めません（親の課題を指定できないため）。');
            }

            if ($status === null) {
                $fail("ステータス「{$statusText}」はこのプロジェクトにありません。");
            }

            if ($priority === null) {
                $fail("優先度「{$priorityText}」はありません（高 / 中 / 低）。");
            }

            if ($email !== '' && $assignee === null) {
                $fail("担当者「{$email}」はこのプロジェクトのメンバーではありません。");
            }

            if ($points !== '' && (! ctype_digit($points) || (int) $points > 999)) {
                $fail('ストーリーポイントは 0〜999 の整数にしてください。');
            }

            if ($estimate !== '' && $minutes === null) {
                $fail('見積もり（分）は分の整数か「3h」のような書き方にしてください。');
            }

            if ($due !== '' && $dueDate === null) {
                $fail("期限「{$due}」が読めません（2026-10-31 の形）。");
            }

            if (count($errors) > $before || count($errors) >= self::MAX_ERRORS) {
                continue;
            }

            $prepared[] = [
                'attributes' => [
                    'title' => $title,
                    'content' => self::paragraphs($values['説明'] ?? ''),
                    'issue_type' => $type,
                    'priority' => $priority,
                    'status_id' => $status->id,
                    'completed_at' => $status->isDone() ? now() : null,
                    'assignee_id' => $assignee?->id,
                    'story_points' => $points === '' ? null : (int) $points,
                    'original_estimate_minutes' => $minutes,
                    'due_date' => $dueDate?->toDateString(),
                ],
                'tags' => array_values(array_filter(array_map(
                    fn (string $name) => mb_substr(trim($name), 0, 30),
                    explode(IssueCsv::TAG_SEPARATOR, $values['タグ'] ?? ''),
                ))),
            ];
        }

        return [$prepared, $errors];
    }

    /**
     * ログイン中の人を外した状態で動かし、終わったら戻す。
     */
    private function withoutActor(callable $callback): void
    {
        $previous = Auth::user();
        Auth::forgetUser();

        try {
            $callback();
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            }
        }
    }

    /**
     * タグ名から、取り込んだ人のタグの ID を引く。無ければその人のタグとして作る。
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(User $user, array $names): array
    {
        return array_map(
            fn (string $name) => $user->tags()->firstOrCreate(['name' => $name], ['color' => TagColor::Slate])->id,
            array_unique($names),
        );
    }

    /**
     * @param  list<array{row: int, message: string}>  $errors
     */
    private function finish(IssueImport $import, ImportStatus $status, int $total, int $imported, array $errors): void
    {
        $import->forceFill([
            'status' => $status,
            'total_rows' => $total,
            'imported_rows' => $imported,
            'errors' => $errors === [] ? null : $errors,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * 英語の値（bug）でも画面の名前（バグ）でも受ける。
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private static function enumByLabel(string $enum, string $text): mixed
    {
        $lower = mb_strtolower($text);

        return $enum::tryFrom($lower)
            ?? collect($enum::cases())->first(fn ($case) => $case->label() === $text);
    }

    private static function date(string $value): ?Carbon
    {
        if (! preg_match('#^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$#', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * 平文の説明を段落の HTML にする。保存時にさらにサニタイズされる（Issue の content ミューテータ）。
     */
    private static function paragraphs(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return collect(preg_split('/\R{2,}/', $text))
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }
}
