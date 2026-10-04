<?php

namespace App\Support;

use App\Models\Project;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * 本文中の @メンション（<span data-type="mention" data-id="5">@名前</span>）を扱う。
 *
 * 印はエディタ（Tiptap の Mention 拡張）が付けるが、HTML は利用者が書き換えて
 * 送れるので、data-id も表示名も信用しない。保存の直前に normalize() を通し、
 *  - プロジェクトのメンバーを指すもの … 表示名をその人の今の名前に書き直す
 *  - それ以外（部外者・存在しない id）… 印を外して、ただの文字に戻す
 * としておけば、「@社長」と書いて別人に通知を飛ばす、といった詐称ができない。
 */
class Mentions
{
    /**
     * 本文が指しているユーザーの id。重複は除く。
     *
     * @return list<int>
     */
    public static function extractIds(?string $html): array
    {
        if (blank($html) || ! str_contains($html, 'data-type="mention"')) {
            return [];
        }

        $ids = [];

        foreach (self::mentionNodes(self::load($html)) as $node) {
            $id = (int) $node->getAttribute('data-id');

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * メンションの印を、プロジェクトのメンバーだけに絞って書き直す。
     * メンションを含まない本文は、手を付けずにそのまま返す。
     */
    public static function normalize(?string $html, Project $project): ?string
    {
        if (blank($html) || ! str_contains($html, 'data-type="mention"')) {
            return $html;
        }

        $document = self::load($html);
        $nodes = iterator_to_array(self::mentionNodes($document));

        $names = $project->users()
            ->whereKey(array_map(fn (DOMElement $node) => (int) $node->getAttribute('data-id'), $nodes))
            ->pluck('name', 'users.id');

        foreach ($nodes as $node) {
            $name = $names[(int) $node->getAttribute('data-id')] ?? null;

            if ($name === null) {
                // 印だけ外し、書かれていた文字は残す（文章の意味は変えない）
                $node->parentNode->replaceChild($document->createTextNode($node->textContent), $node);

                continue;
            }

            $node->setAttribute('data-label', $name);
            $node->textContent = "@{$name}";
        }

        return self::save($document);
    }

    /**
     * @return iterable<DOMElement>
     */
    private static function mentionNodes(DOMDocument $document): iterable
    {
        return (new DOMXPath($document))->query('//span[@data-type="mention"]');
    }

    /**
     * 断片の HTML を読む。文字化けしないよう、文字コードを明示した殻で包む。
     */
    private static function load(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="mentions-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private static function save(DOMDocument $document): string
    {
        $root = (new DOMXPath($document))->query('//div[@id="mentions-root"]')->item(0);
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
