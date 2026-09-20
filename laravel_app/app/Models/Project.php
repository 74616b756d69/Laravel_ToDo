<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    /** 採番が競合したときに諦めるまでの回数 */
    private const ISSUE_NUMBER_MAX_ATTEMPTS = 25;

    protected $fillable = ['key', 'name', 'description'];

    protected static function booted(): void
    {
        // プロジェクトは必ずワークフローを持つ。ここで入れておけば、
        // 画面からでもシーダーからでもファクトリからでも同じ状態で始まる。
        static::created(fn (Project $project) => app(WorkflowService::class)->installDefaults($project));
    }

    /**
     * roleFor() の結果を user_id ごとに覚えておく。
     * 1 リクエスト中に Policy から何度も同じ判定が走るため。
     *
     * @var array<int, ProjectRole|null>
     */
    private array $roles = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ProjectMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /** @return HasMany<Status, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->ordered();
    }

    /** @return HasMany<Transition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(Transition::class);
    }

    /** @return HasMany<Sprint, $this> */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * 新しい課題が置かれるステータス。並び順のいちばん手前。
     */
    public function initialStatus(): Status
    {
        return $this->statuses()->firstOrFail();
    }

    /**
     * 「完了」として扱うステータス。done カテゴリの先頭。
     */
    public function doneStatus(): Status
    {
        return $this->statuses()->where('category', StatusCategory::Done)->firstOrFail();
    }

    /**
     * 課題を採番付きで作る。課題の作成は必ずここを通す。
     *
     * 採番は「行ロック + DB の unique 制約 + リトライ」の三段構えで守る。
     *  - 行ロック … 平常時の性能のため。MySQL では FOR UPDATE が効いて衝突しない
     *  - unique 制約 … 正しさのため。SQLite では FOR UPDATE が黙って無視されるので最後の砦
     *  - リトライ … 可用性のため。衝突しても採番からやり直せば必ず前に進む
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createIssue(array $attributes): Issue
    {
        // 採番と INSERT を同じトランザクションに入れる。
        // 途中で失敗したら番号ごと巻き戻る（欠番は許容する）
        // ステータス未指定なら初期ステータスへ。課題は必ずワークフロー上に居る
        $attributes['status_id'] ??= $this->initialStatus()->id;

        return $this->retryOnContention(fn () => DB::transaction(function () use ($attributes) {
            // 外部キーを含むので forceFill。fillable は利用者入力ぶんだけに絞ってある
            $issue = $this->issues()->make()->forceFill($attributes);
            $issue->issue_number = $this->nextIssueNumber();
            $issue->save();

            return $issue;
        }));
    }

    /**
     * 次の課題番号を 1 つ払い出す。
     *
     * 自分の 1 行だけをロックするので、他プロジェクトの採番は一切止まらない。
     */
    public function allocateIssueNumber(): int
    {
        return $this->retryOnContention(fn () => DB::transaction(fn () => $this->nextIssueNumber()));
    }

    /**
     * カウンタを 1 つ進めて返す。呼び出し側のトランザクションの中で動く前提。
     *
     * MySQL では FOR UPDATE が効き、この行を掴んだ側以外は待たされる。
     * SQLite では FOR UPDATE が黙って無視されるため、ここは競合しうる。
     * だから unique(project_id, issue_number) 制約とリトライで受け止める。
     */
    private function nextIssueNumber(): int
    {
        $next = (int) static::whereKey($this->getKey())
            ->lockForUpdate()
            ->value('last_issue_number') + 1;

        static::whereKey($this->getKey())->update(['last_issue_number' => $next]);

        $this->last_issue_number = $next;

        return $next;
    }

    /**
     * 採番の競合を吸収する。
     *
     * 拾うのは 2 種類:
     *  - unique 制約違反 … 同じ番号を 2 人が掴んだ（SQLite で起こりうる）
     *  - ロック競合 … SQLite の "database is locked"、MySQL のデッドロック / ロック待ちタイムアウト
     *
     * どちらも「やり直せば必ず前に進む」類の失敗なので、待ち時間をばらしながら再試行する。
     * 待ちを乱数にするのは、同時に落ちた者同士が同じ間隔で再突入して衝突し続けるのを避けるため。
     *
     * @template TValue
     *
     * @param  callable(): TValue  $operation
     * @return TValue
     */
    private function retryOnContention(callable $operation): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (UniqueConstraintViolationException|QueryException $e) {
                if ($attempt >= self::ISSUE_NUMBER_MAX_ATTEMPTS || ! $this->isContention($e)) {
                    throw $e;
                }

                usleep(random_int(1_000, 10_000) * $attempt);
            }
        }
    }

    /**
     * やり直せば直る類の失敗か。それ以外は握りつぶさずそのまま投げる。
     */
    private function isContention(QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        return Str::contains($e->getMessage(), [
            'database is locked',   // SQLite
            'database table is locked',
            'Deadlock found',       // MySQL 1213
            'Lock wait timeout',    // MySQL 1205
        ]);
    }

    /** @return BelongsToMany<User, $this, ProjectMember> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership')
            ->orderBy('name');
    }

    /**
     * そのユーザーの個人プロジェクトを返す。まだ無ければ作る。
     *
     * 移行コマンドと、プロジェクト未指定での課題作成の両方がここを通る。
     * プロジェクト選択 UI が入ったら、既定値を決めるためだけの存在になる。
     */
    public static function personalFor(User $user): self
    {
        $organization = Organization::personalFor($user);

        $existing = $organization->projects()
            ->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->oldest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $project = $organization->projects()->create([
            'key' => self::uniqueKey($user),
            'name' => "{$user->name} の課題",
            'description' => '個人用のプロジェクトです。',
        ]);

        $project->members()->create(['user_id' => $user->id, 'role' => ProjectRole::Admin]);

        return $project;
    }

    /**
     * 課題キーの候補を作る。名前 → メールのローカル部 → ユーザー ID の順に英字を拾う。
     *
     * 連番に数字を使わないのは、キーが「大文字英字 2〜10 桁」だからで、
     * ここで作ったプロジェクトが後から編集フォームを通らなくなるのを防ぐため。
     */
    private static function uniqueKey(User $user): string
    {
        $base = collect([$user->name, Str::before($user->email, '@'), "U{$user->id}"])
            ->map(fn (?string $seed) => Str::upper(preg_replace('/[^A-Za-z]/', '', (string) $seed) ?? ''))
            ->first(fn (string $candidate) => strlen($candidate) >= 2) ?? 'PROJ';

        $base = substr($base, 0, 8);
        $key = $base;

        for ($n = 1; self::where('key', $key)->exists(); $n++) {
            $key = $base.self::alphabeticSuffix($n);
        }

        return $key;
    }

    /**
     * 1, 2, 3... を A, B, C... AA, AB... に変換する（Excel の列名と同じ考え方）。
     */
    private static function alphabeticSuffix(int $n): string
    {
        $suffix = '';

        while ($n > 0) {
            $n--;
            $suffix = chr(ord('A') + $n % 26).$suffix;
            $n = intdiv($n, 26);
        }

        return $suffix;
    }

    /**
     * 指定ユーザーのこのプロジェクトでの役割。所属していなければ null。
     */
    public function roleFor(User $user): ?ProjectRole
    {
        if (! array_key_exists($user->id, $this->roles)) {
            // value() はモデル経由なのでキャストが効き、ProjectRole または null が返る
            $this->roles[$user->id] = $this->members()->where('user_id', $user->id)->value('role');
        }

        return $this->roles[$user->id];
    }

    public function hasMember(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }

    public function isAdmin(User $user): bool
    {
        return $this->roleFor($user) === ProjectRole::Admin;
    }

    /**
     * 自分が所属しているプロジェクトだけに絞る。
     * 一覧や検索は Policy では守れないので、入口で必ずこれを通す。
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id));
    }
}
