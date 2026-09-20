<?php

namespace Tests\Unit;

use App\Support\RichText;
use Tests\TestCase;

/**
 * 保存前の無害化は XSS 対策の要なので、単体で重点的に検証する。
 */
class RichTextTest extends TestCase
{
    public function test_許可されたタグは残る(): void
    {
        $html = RichText::sanitize('<p>ふつうの<strong>太字</strong>と<em>斜体</em></p><h2>見出し</h2>');

        $this->assertStringContainsString('<strong>太字</strong>', $html);
        $this->assertStringContainsString('<h2>見出し</h2>', $html);
    }

    public function test_チェックリストの構造が保持される(): void
    {
        $html = RichText::sanitize(
            '<ul data-type="taskList"><li data-checked="true" data-type="taskItem">'
            .'<label><input type="checkbox" checked="checked"><span></span></label>'
            .'<div><p>買い物</p></div></li></ul>'
        );

        $this->assertStringContainsString('data-type="taskList"', $html);
        $this->assertStringContainsString('data-checked="true"', $html);
        $this->assertStringContainsString('<input type="checkbox"', $html);
    }

    public function test_scriptタグとイベント属性は除去される(): void
    {
        $html = RichText::sanitize('<p onclick="alert(1)">本文</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('本文', $html);
    }

    public function test_javascriptスキームのリンクは除去される(): void
    {
        $html = RichText::sanitize('<p><a href="javascript:alert(1)">危険なリンク</a></p>');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_imgタグは許可しない(): void
    {
        $this->assertNull(RichText::sanitize('<img src="x" onerror="alert(1)">'));
    }

    public function test_中身が空なら_nullになる(): void
    {
        $this->assertNull(RichText::sanitize('<p></p>'));
        $this->assertNull(RichText::sanitize(''));
        $this->assertNull(RichText::sanitize(null));
    }

    public function test_平文への変換で単語が繋がらない(): void
    {
        $this->assertSame(
            '一行目 二行目',
            RichText::toPlainText('<p>一行目</p><p>二行目</p>'),
        );
    }

    public function test_抜粋は指定幅で打ち切られる(): void
    {
        $excerpt = RichText::excerpt('<p>'.str_repeat('あ', 200).'</p>', 20);

        // Str::limit は表示幅で数えるため、全角は 1 文字あたり 2 として扱われる
        $this->assertSame(str_repeat('あ', 10).'...', $excerpt);
        $this->assertSame(20, mb_strwidth($excerpt) - 3);
    }

    public function test_閲覧用_htmlではチェックボックスが無効化される(): void
    {
        $html = RichText::forDisplay('<input type="checkbox" checked="checked">');

        $this->assertStringContainsString('disabled', $html);
    }
}
