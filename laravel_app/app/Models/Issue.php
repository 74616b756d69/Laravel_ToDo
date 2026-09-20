<?php

namespace App\Models;

use App\Enums\IssueType;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Observers\IssueObserver;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 課題。
 *
 * テーブル名は移行の都合で tasks のまま。URL（/tasks/*）も据え置きなので、
 * 既存のリンクとブックマークはそのまま生きる。
 */
#[ObservedBy(IssueObserver::class)]
class Issue extends Model
{
    /** @use HasFactory<\Database\Factories\IssueFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tasks';

    /**
     * 利用者がフォームから直接決めてよい項目だけ。
     *
     * status_id / sprint_id / parent_id / reporter_id などの外部キーは
     * 意図的に外してある。これらを動かせるのは、遷移や移送の妥当性を
     * 検査するサービス（WorkflowService / SprintService / Project::createIssue）だけで、
     * そちらは forceFill で書く。
     */
    protected $fillable = [
        'title',
        'content',
        'priority',
        'due_date',
        'position',
        'story_points',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'issue_type' => IssueType::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // 採番漏れの保険。通常は Project::createIssue() が入れてくれるが、
        // ファクトリやシーダーから直接作られたときもキーが必ず振られるようにする。
        static::creating(function (Issue $issue) {
            if ($issue->issue_number === null && $issue->project_id !== null) {
                $issue->issue_number = $issue->project->allocateIssueNumber();
            }
        });

        // 子課題は独立した行なので、親を消したら一緒に消さないと一覧に孤児が残る。
        // 外部キーのカスケードはハードデリートにしか効かないため、ここで面倒を見る。
        static::deleting(function (Issue $issue) {
            if ($issue->isForceDeleting()) {
                return;
            }

            $issue->children()->each(fn (Issue $child) => $child->delete());
        });

        static::restoring(function (Issue $issue) {
            $issue->children()->onlyTrashed()->each(fn (Issue $child) => $child->restore());
        });
    }

    // --- 関連 ---------------------------------------------------------------

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 現在のステータス。プロジェクトごとに定義されたワークフローの 1 状態。
     *
     * 書き換えは必ず WorkflowService::transition() を通すこと。
     * ここを直接 update すると、許可されていない遷移が素通りする。
     *
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * 所属スプリント。null はバックログに居ることを表す。
     *
     * 出し入れは SprintService::assign() を通すこと。
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** 起票者。 @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** 担当者。未割り当てなら null。 @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<Issue, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Issue, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /**
     * コメント。
     *
     * chaperone() で逆向きの issue を張っておく。CommentPolicy が
     * $comment->issue からプロジェクトを引くため、これが無いと
     * コメントの数だけ遅延ロードが走る（strict 下では例外になる）。
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest()->chaperone();
    }

    /**
     * 変更履歴。不変なので追記しか起こらない。
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->oldest();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_task', 'task_id', 'tag_id')->orderBy('name');
    }

    // --- 表示 ---------------------------------------------------------------

    /**
     * 表示用の課題キー（PROJ-123）。
     *
     * 保存しないのは、プロジェクトキーを変えたときに全行 UPDATE したくないため。
     * 呼ぶ画面では project を eager load しておくこと（shouldBeStrict のため）。
     */
    public function key(): string
    {
        return "{$this->project->key}-{$this->issue_number}";
    }

    /**
     * content は HTML として保存する。保存の直前に必ず無害化し、
     * 検索用の平文（content_text）も同時に更新する。
     */
    protected function content(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $html = RichText::sanitize($value);

            return [
                'content' => $html,
                'content_text' => $html === null ? null : RichText::toPlainText($html),
            ];
        });
    }

    public function excerpt(int $limit = 120): string
    {
        return RichText::excerpt($this->content, $limit);
    }

    /**
     * 子課題の進捗率（0-100）。子が無ければ null。
     *
     * 完了判定にステータスを見るので、呼ぶ側は children.status まで
     * eager load しておくこと（shouldBeStrict のため）。
     */
    public function progress(): ?int
    {
        $total = $this->children->count();

        if ($total === 0) {
            return null;
        }

        $done = $this->children->filter(fn (Issue $child) => $child->isCompleted())->count();

        return (int) round($done / $total * 100);
    }

    /**
     * 完了かどうかはステータス名ではなくカテゴリで判断する。
     * 「Done」を「リリース済み」に改名しても壊れないようにするため。
     */
    public function isCompleted(): bool
    {
        return $this->status->isDone();
    }

    /**
     * 期限切れ（未完了かつ期限が過ぎている）。
     */
    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    /**
     * 期限が今日・明日など「もうすぐ」かどうか。
     */
    public function isDueSoon(): bool
    {
        return ! $this->isCompleted()
            && ! $this->isOverdue()
            && $this->due_date !== null
            && $this->due_date->isBefore(today()->addDays(3));
    }

    // --- スコープ -------------------------------------------------------------

    /**
     * 自分が参加しているプロジェクトの課題だけに絞る。
     *
     * 一覧は Policy では守れないので、入口で必ずこれを通す。
     * 認可の根拠（project_members.role）と同じものを見ていることが大事。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas(
            'project.members',
            fn (Builder $query) => $query->where('user_id', $user->id),
        );
    }

    /**
     * まだどのスプリントにも入っていない課題。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeBacklog(Builder $query): void
    {
        $query->whereNull('sprint_id');
    }

    /**
     * 単独のカードとして並ぶ課題だけ。
     *
     * 判定は parent_id だけで行う。issue_type は課題の性質（Bug / Story）を
     * 表すもので、「親の下にいるか」とは別の軸だから。
     * 子は親の詳細画面に表示するので、一覧・ボード・バックログ・分析からは外す。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * 親を持っているか（＝どこかの課題のサブタスクか）。
     */
    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * タイトル・本文の部分一致検索。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeSearch(Builder $query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return;
        }

        // LIKE のワイルドカードを打ち消してから部分一致に使う
        $escaped = addcslashes($keyword, '%_\\');

        // content は HTML なのでタグに引っかからないよう平文カラムを検索する
        $query->where(function (Builder $query) use ($escaped) {
            $query->where('title', 'like', "%{$escaped}%")
                ->orWhere('content_text', 'like', "%{$escaped}%");
        });
    }

    /**
     * 指定ステータスの課題だけに絞る。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeStatus(Builder $query, ?int $statusId): void
    {
        $query->when($statusId, fn (Builder $query) => $query->where('status_id', $statusId));
    }

    /**
     * ステータスのカテゴリで絞る。
     *
     * ステータス名はプロジェクトごとに違うので、プロジェクトを横断した
     * 「未着手」「進行中」の絞り込みはこちらを使う。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeCategory(Builder $query, ?StatusCategory $category): void
    {
        $query->when($category, fn (Builder $query) => $query->whereHas(
            'status',
            fn (Builder $query) => $query->where('category', $category),
        ));
    }

    /**
     * 完了カテゴリのステータスに居る / 居ない課題に絞る。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeCompleted(Builder $query, bool $completed = true): void
    {
        $query->whereHas(
            'status',
            fn (Builder $query) => $completed
                ? $query->where('category', StatusCategory::Done)
                : $query->where('category', '!=', StatusCategory::Done),
        );
    }

    /** @param  Builder<Issue>  $query */
    public function scopePriority(Builder $query, ?TaskPriority $priority): void
    {
        $query->when($priority, fn (Builder $query) => $query->where('priority', $priority));
    }

    /**
     * 指定タグが付いた課題だけに絞る。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeTagged(Builder $query, ?int $tagId): void
    {
        $query->when($tagId, fn (Builder $query) => $query->whereHas(
            'tags',
            fn (Builder $query) => $query->where('tags.id', $tagId),
        ));
    }

    /** @param  Builder<Issue>  $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->completed(false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    /**
     * 指定キーで並び替える。優先度は enum の重み順に並べたいので CASE 式を使う。
     *
     * @param  Builder<Issue>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        match ($sort) {
            'due_date' => $query
                // 期限なしは最後に回す
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->orderByDesc('id'),
            'priority' => $query
                ->orderByRaw(self::priorityCaseExpression().' desc')
                ->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    private static function priorityCaseExpression(): string
    {
        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority) => sprintf(
                "when '%s' then %d",
                $priority->value,
                $priority->weight(),
            ))
            ->implode(' ');

        return "case priority {$cases} else 0 end";
    }
}
