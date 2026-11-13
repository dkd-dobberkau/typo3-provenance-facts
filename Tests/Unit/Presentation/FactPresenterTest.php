<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Unit\Presentation;

use CmsProvenance\ProvenanceFacts\Domain\Model\Fact;
use CmsProvenance\ProvenanceFacts\Presentation\FactPresenter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FactPresenterTest extends UnitTestCase
{
    private const PAGE = 'https://example.org/report';
    private const ANNOTATION = 'https://example.org/.well-known/content-provenance/facts/revenue-2025';

    #[Test]
    public function claimCarriesTheStructuredStatement(): void
    {
        $claim = (new FactPresenter())->claim($this->fact(), self::PAGE);

        self::assertSame([
            '@type' => 'Claim',
            '@id' => 'https://example.org/report#fact-revenue-2025',
            'text' => '12.4 million EUR',
            'subject' => 'https://example.org/company',
            'predicate' => 'https://schema.org/revenue',
            'object' => '12400000',
            'evidence' => 'https://example.org/annual-report.pdf',
            'confidence' => 'measured',
            'validFrom' => '2025-01-01',
            'validUntil' => '2025-12-31',
        ], $claim);
    }

    #[Test]
    public function predicateWithoutSchemeIsASchemaOrgTerm(): void
    {
        $claim = (new FactPresenter())->claim($this->fact(predicate: 'numberOfEmployees'), self::PAGE);

        self::assertSame('https://schema.org/numberOfEmployees', $claim['predicate']);
    }

    #[Test]
    public function objectUriTakesPrecedenceOverTheLiteral(): void
    {
        $claim = (new FactPresenter())->claim($this->fact(objectUri: 'https://www.wikidata.org/wiki/Q64'), self::PAGE);

        self::assertSame(['@id' => 'https://www.wikidata.org/wiki/Q64'], $claim['object']);
    }

    #[Test]
    public function annotationTargetsTheFragmentAndTheQuotedText(): void
    {
        $annotation = (new FactPresenter())->annotation($this->fact(), self::PAGE, self::ANNOTATION);

        self::assertSame(['http://www.w3.org/ns/anno.jsonld', 'https://cms-provenance.org/ns/v1'], $annotation['@context']);
        self::assertSame(self::ANNOTATION, $annotation['id']);
        self::assertSame('Annotation', $annotation['type']);
        self::assertSame('describing', $annotation['motivation']);
        self::assertSame('2026-11-13T09:00:00Z', $annotation['created']);
        self::assertSame(self::PAGE, $annotation['target']['source']);
        self::assertSame([
            ['type' => 'FragmentSelector', 'conformsTo' => 'http://tools.ietf.org/rfc/rfc3986', 'value' => 'fact-revenue-2025'],
            ['type' => 'TextQuoteSelector', 'exact' => '12.4 million EUR'],
        ], $annotation['target']['selector']);
        self::assertSame('https://example.org/report#fact-revenue-2025', $annotation['body']['id']);
        self::assertSame('https://schema.org/revenue', $annotation['body']['predicate']);
    }

    #[Test]
    public function validityDatesAreTheCalendarDaysTheEditorEnteredInTheServerTimeZone(): void
    {
        $previousTimeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        try {
            // TYPO3 stores a date field as midnight in the server time zone.
            $midnight = static fn(string $day): int => (new \DateTimeImmutable($day, new \DateTimeZone('Europe/Berlin')))->getTimestamp();
            $fact = new Fact(
                factId: 'revenue-2025',
                quote: '12.4 million EUR',
                subject: 'https://example.org/company',
                predicate: 'revenue',
                objectLiteral: '12400000',
                objectUri: null,
                sourceUrl: null,
                confidence: 'measured',
                validFrom: $midnight('2025-01-01'),
                validUntil: $midnight('2025-12-31'),
                createdAt: 1794560400,
            );

            $claim = (new FactPresenter())->claim($fact, self::PAGE);
        } finally {
            date_default_timezone_set($previousTimeZone);
        }

        self::assertSame('2025-01-01', $claim['validFrom']);
        self::assertSame('2025-12-31', $claim['validUntil']);
    }

    private function fact(string $predicate = 'https://schema.org/revenue', ?string $objectUri = null): Fact
    {
        return new Fact(
            factId: 'revenue-2025',
            quote: '12.4 million EUR',
            subject: 'https://example.org/company',
            predicate: $predicate,
            objectLiteral: '12400000',
            objectUri: $objectUri,
            sourceUrl: 'https://example.org/annual-report.pdf',
            confidence: 'measured',
            validFrom: 1735689600,
            validUntil: 1767139200,
            createdAt: 1794560400,
        );
    }
}
