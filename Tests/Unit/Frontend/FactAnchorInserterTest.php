<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Unit\Frontend;

use CmsProvenance\ProvenanceFacts\Domain\Model\Fact;
use CmsProvenance\ProvenanceFacts\Frontend\FactAnchorInserter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FactAnchorInserterTest extends UnitTestCase
{
    #[Test]
    public function wrapsTheQuoteInAnAddressableClaimElement(): void
    {
        $html = (new FactAnchorInserter())->insert('<p>Revenue grew to 12.4 million EUR in 2025.</p>', [$this->fact('revenue-2025', '12.4 million EUR')]);

        self::assertSame(
            '<p>Revenue grew to <span id="fact-revenue-2025" class="cps-fact" itemscope itemtype="https://schema.org/Claim">'
            . '<span itemprop="text">12.4 million EUR</span></span> in 2025.</p>',
            $html
        );
    }

    #[Test]
    public function findsQuotesWrittenWithEntities(): void
    {
        $html = (new FactAnchorInserter())->insert('<p>Profit &amp; loss: 3&nbsp;%</p>', [$this->fact('pl', "Profit & loss: 3\u{00A0}%")]);

        self::assertStringContainsString('<span itemprop="text">Profit &amp; loss: 3' . "\u{00A0}" . '%</span>', $html);
        self::assertSame(
            html_entity_decode(strip_tags('<p>Profit &amp; loss: 3&nbsp;%</p>'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }

    #[Test]
    public function wrapsOnlyTheFirstOccurrence(): void
    {
        $html = (new FactAnchorInserter())->insert('<p>42 and 42</p>', [$this->fact('answer', '42')]);

        self::assertSame(1, substr_count($html, 'id="fact-answer"'));
        self::assertStringEndsWith(' and 42</p>', $html);
    }

    #[Test]
    public function neverMatchesInsideTagsOrAttributes(): void
    {
        $original = '<p><a href="https://example.org/42" title="42">link</a></p>';

        self::assertSame($original, (new FactAnchorInserter())->insert($original, [$this->fact('answer', '42')]));
    }

    #[Test]
    public function quoteSpanningMarkupIsLeftUnanchored(): void
    {
        $original = '<p>Revenue <strong>grew</strong> strongly</p>';

        self::assertSame($original, (new FactAnchorInserter())->insert($original, [$this->fact('growth', 'Revenue grew')]));
    }

    #[Test]
    public function textInsideScriptsIsNotTouched(): void
    {
        $original = '<script>var x = "42";</script><p>none</p>';

        self::assertSame($original, (new FactAnchorInserter())->insert($original, [$this->fact('answer', '42')]));
    }

    private function fact(string $id, string $quote): Fact
    {
        return new Fact(
            factId: $id,
            quote: $quote,
            subject: 'https://example.org/company',
            predicate: 'https://schema.org/revenue',
            objectLiteral: '12400000',
            objectUri: null,
            sourceUrl: null,
            confidence: 'asserted',
            validFrom: null,
            validUntil: null,
            createdAt: 1794560400,
        );
    }
}
