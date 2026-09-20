<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Models\Tag;
use App\Support\QuickAddParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickAddParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 曜日がらみの判定があるため、基準日を固定する（2026-09-24 は木曜）
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function parser(array $tagNames = ['仕事', '学習']): QuickAddParser
    {
        $tags = collect($tagNames)->map(
            fn (string $name, int $index) => new Tag(['name' => $name])->forceFill(['id' => $index + 1]),
        );

        return new QuickAddParser($tags);
    }

    public function test_記法が無ければ全体がタイトルになる(): void
    {
        $parsed = $this->parser()->parse('請求書を送る');

        $this->assertSame('請求書を送る', $parsed->title);
        $this->assertNull($parsed->dueDate);
        $this->assertSame(TaskPriority::Medium, $parsed->priority);
        $this->assertSame([], $parsed->tagIds);
    }

    public function test_全部入りの入力を解釈できる(): void
    {
        $parsed = $this->parser()->parse('明日 請求書を送る #仕事 !高');

        $this->assertSame('請求書を送る', $parsed->title);
        $this->assertSame('2026-09-25', $parsed->dueDate->toDateString());
        $this->assertSame(TaskPriority::High, $parsed->priority);
        $this->assertSame([1], $parsed->tagIds);
    }

    #[DataProvider('優先度の表記')]
    public function test_優先度の表記ゆれを受け付ける(string $token, TaskPriority $expected): void
    {
        $parsed = $this->parser()->parse("資料を作る {$token}");

        $this->assertSame($expected, $parsed->priority);
        $this->assertSame('資料を作る', $parsed->title);
    }

    public static function 優先度の表記(): array
    {
        return [
            ['!高', TaskPriority::High],
            ['!high', TaskPriority::High],
            ['!H', TaskPriority::High],
            ['!中', TaskPriority::Medium],
            ['!低', TaskPriority::Low],
            ['!low', TaskPriority::Low],
        ];
    }

    #[DataProvider('日付の表記')]
    public function test_日付の表記を解釈できる(string $token, string $expected): void
    {
        $parsed = $this->parser()->parse("{$token} 資料を作る");

        $this->assertSame($expected, $parsed->dueDate?->toDateString(), "「{$token}」の解釈");
        $this->assertSame('資料を作る', $parsed->title);
    }

    public static function 日付の表記(): array
    {
        // 基準日は 2026-09-24（木）
        return [
            '今日' => ['今日', '2026-09-24'],
            '明日' => ['明日', '2026-09-25'],
            '明後日' => ['明後日', '2026-09-26'],
            '来週' => ['来週', '2026-10-01'],
            '来月' => ['来月', '2026-10-24'],
            '3日後' => ['3日後', '2026-09-27'],
            '2週間後' => ['2週間後', '2026-10-08'],
            '次の月曜' => ['月曜', '2026-09-28'],
            '曜日（日）' => ['日曜日', '2026-09-27'],
            '今週金曜' => ['今週金曜', '2026-09-25'],
            '来週月曜' => ['来週月曜', '2026-09-28'],
            '来週日曜' => ['来週日曜', '2026-10-04'],
            '今週末' => ['今週末', '2026-09-26'],
            '今月末' => ['今月末', '2026-09-30'],
            '月日' => ['10/3', '2026-10-03'],
            '年月日' => ['2027-01-09', '2027-01-09'],
        ];
    }

    public function test_過ぎた月日は翌年として扱う(): void
    {
        $parsed = $this->parser()->parse('9/1 健康診断');

        $this->assertSame('2027-09-01', $parsed->dueDate->toDateString());
    }

    public function test_全角で入力しても解釈できる(): void
    {
        $parsed = $this->parser()->parse('３日後　資料を作る　＃仕事　！高');

        $this->assertSame('資料を作る', $parsed->title);
        $this->assertSame('2026-09-27', $parsed->dueDate->toDateString());
        $this->assertSame(TaskPriority::High, $parsed->priority);
        $this->assertSame([1], $parsed->tagIds);
    }

    public function test_複数のタグを指定できる(): void
    {
        $parsed = $this->parser()->parse('論文を読む #仕事 #学習');

        $this->assertSame([1, 2], $parsed->tagIds);
        $this->assertSame('論文を読む', $parsed->title);
    }

    public function test_登録の無いタグは採用せず報告する(): void
    {
        $parsed = $this->parser()->parse('買い物 #存在しないタグ');

        $this->assertSame([], $parsed->tagIds);
        $this->assertSame(['存在しないタグ'], $parsed->unknownTags);
        $this->assertSame('買い物', $parsed->title);
    }

    public function test_日付は最初の1つだけを消費し本文の日付語は残す(): void
    {
        $parsed = $this->parser()->parse('明日 今日の分の日報を書く');

        $this->assertSame('2026-09-25', $parsed->dueDate->toDateString());
        // 本文に含まれる「今日」はタイトルとして残る
        $this->assertSame('今日の分の日報を書く', $parsed->title);
    }

    public function test_タイトルが空になる入力を判別できる(): void
    {
        $parsed = $this->parser()->parse('明日 !高');

        $this->assertFalse($parsed->hasTitle());
    }

    public function test_空文字を渡しても壊れない(): void
    {
        foreach (['', '   ', null] as $input) {
            $parsed = $this->parser()->parse($input);

            $this->assertFalse($parsed->hasTitle());
            $this->assertNull($parsed->dueDate);
        }
    }

    public function test_記号が本文に含まれていても壊れない(): void
    {
        $parsed = $this->parser()->parse('APIの設計を見直す(v2)');

        $this->assertSame('APIの設計を見直す(v2)', $parsed->title);
    }
}
