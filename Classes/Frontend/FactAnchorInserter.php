<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Frontend;

use CmsProvenance\ProvenanceFacts\Domain\Model\Fact;

/**
 * Makes facts addressable in rendered HTML: the first occurrence of a fact's
 * quote inside a single text node is wrapped in an element with the id
 * "fact-{factId}" and schema.org Claim Microdata. The visible text does not
 * change, so the canonical text and a signature over it stay the same.
 *
 * A quote that spans markup (for example "Revenue <strong>grew</strong>") is
 * left unanchored; the Web Annotation still identifies it by its text.
 */
final class FactAnchorInserter
{
    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'textarea', 'title'];

    /**
     * @param list<Fact> $facts
     */
    public function insert(string $html, array $facts): string
    {
        foreach ($facts as $fact) {
            $html = $this->anchor($html, $fact);
        }

        return $html;
    }

    private function anchor(string $html, Fact $fact): string
    {
        if ($fact->quote === '') {
            return $html;
        }

        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $rawTextElement = null;
        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '<')) {
                $rawTextElement = $this->trackRawText($part, $rawTextElement);
                continue;
            }
            if ($rawTextElement !== null || $part === '') {
                continue;
            }

            $text = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $position = mb_strpos($text, $fact->quote);
            if ($position === false) {
                continue;
            }

            $before = mb_substr($text, 0, $position);
            $after = mb_substr($text, $position + mb_strlen($fact->quote));
            $parts[$index] = $this->encode($before)
                . sprintf(
                    '<span id="%s" class="cps-fact" itemscope itemtype="https://schema.org/Claim"><span itemprop="text">%s</span></span>',
                    htmlspecialchars($fact->fragment(), ENT_QUOTES | ENT_HTML5),
                    $this->encode($fact->quote)
                )
                . $this->encode($after);

            return implode('', $parts);
        }

        return $html;
    }

    private function trackRawText(string $tag, ?string $current): ?string
    {
        if ($current !== null) {
            return preg_match('#^</\s*' . preg_quote($current, '#') . '\b#i', $tag) === 1 ? null : $current;
        }
        if (preg_match('#^<\s*([a-z0-9]+)#i', $tag, $matches) === 1 && in_array(strtolower($matches[1]), self::RAW_TEXT_ELEMENTS, true)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function encode(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }
}
