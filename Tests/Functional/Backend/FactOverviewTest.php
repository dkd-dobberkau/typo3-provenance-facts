<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional\Backend;

use CmsProvenance\ProvenanceFacts\Backend\FactFilter;
use CmsProvenance\ProvenanceFacts\Backend\FactOverview;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FactOverviewTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FactOverview.csv');
    }

    #[Test]
    public function listsLiveDefaultLanguageFactsOfTheGivenPagesInOrder(): void
    {
        self::assertSame(
            ['ashmolean-opened', 'draft-fact', 'in-hidden-element', 'sale', 'other-sale', 'on-hidden-page'],
            $this->factIds($this->find())
        );
    }

    #[Test]
    public function hiddenThingsAreMarked(): void
    {
        $rows = array_column($this->find()['rows'], null, 'factId');

        self::assertTrue($rows['draft-fact']['factHidden']);
        self::assertTrue($rows['in-hidden-element']['contentHidden']);
        self::assertTrue($rows['on-hidden-page']['pageHidden']);
        self::assertFalse($rows['ashmolean-opened']['factHidden'] || $rows['ashmolean-opened']['contentHidden'] || $rows['ashmolean-opened']['pageHidden']);
    }

    #[Test]
    public function rowsCarryTheFactAndItsPage(): void
    {
        $row = $this->find()['rows'][0];

        self::assertSame('opened in 1683', $row['quote']);
        self::assertSame('http://www.wikidata.org/entity/Q636400', $row['subject']);
        self::assertSame('foundingDate', $row['predicate']);
        self::assertSame('1683', $row['objectLiteral']);
        self::assertSame('https://en.wikipedia.org/wiki/Ashmolean_Museum', $row['sourceUrl']);
        self::assertSame(1, $row['contentUid']);
        self::assertSame(1, $row['pageUid']);
        self::assertSame('Root', $row['pageTitle']);
    }

    #[Test]
    public function searchCoversIdQuoteSubjectValueAndEvidenceIgnoringCase(): void
    {
        self::assertSame(['ashmolean-opened'], $this->factIds($this->find(new FactFilter(search: 'OPENED'))));
        self::assertSame(['draft-fact'], $this->factIds($this->find(new FactFilter(search: 'Q471406'))));
        self::assertSame(['ashmolean-opened'], $this->factIds($this->find(new FactFilter(search: 'wikipedia'))));
        self::assertSame(['ashmolean-opened', 'draft-fact'], $this->factIds($this->find(new FactFilter(search: 'q636400'))));
    }

    #[Test]
    public function searchTreatsWildcardsAndQuotesLiterally(): void
    {
        self::assertSame(['sale'], $this->factIds($this->find(new FactFilter(search: '50%_off"'))));
    }

    #[Test]
    public function filtersByPredicateAndConfidence(): void
    {
        self::assertSame(['ashmolean-opened'], $this->factIds($this->find(new FactFilter(predicate: 'foundingDate'))));
        self::assertSame(['sale', 'other-sale'], $this->factIds($this->find(new FactFilter(confidence: 'measured'))));
    }

    #[Test]
    public function predicatesAreDistinctAndSorted(): void
    {
        self::assertSame(['description', 'founder', 'foundingDate'], $this->get(FactOverview::class)->predicates([1, 2, 3]));
    }

    #[Test]
    public function noPagesGiveNoFacts(): void
    {
        $result = $this->get(FactOverview::class)->find([], new FactFilter(), 1);

        self::assertSame([], $result['rows']);
        self::assertSame(0, $result['total']);
    }

    #[Test]
    public function resultsArePaginatedAndThePageIsClamped(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_provenancefacts_fact');
        for ($i = 1; $i <= 114; $i++) {
            $connection->insert('tx_provenancefacts_fact', ['pid' => 1, 'content_uid' => 1, 'sorting' => 2000 + $i, 'fact_id' => 'bulk-' . $i, 'quote' => 'x', 'subject' => 'https://example.org/', 'predicate' => 'description', 'object_literal' => 'v', 'confidence' => 'asserted']);
        }

        $first = $this->get(FactOverview::class)->find([1, 2, 3], new FactFilter(), 1);
        $beyond = $this->get(FactOverview::class)->find([1, 2, 3], new FactFilter(), 99);

        self::assertSame(120, $first['total']);
        self::assertSame(3, $first['pages']);
        self::assertCount(50, $first['rows']);
        self::assertSame(3, $beyond['page']);
        self::assertCount(20, $beyond['rows']);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    private function find(?FactFilter $filter = null): array
    {
        return $this->get(FactOverview::class)->find([1, 2, 3], $filter ?? new FactFilter(), 1);
    }

    /**
     * @param array{rows: list<array<string, mixed>>} $result
     * @return list<string>
     */
    private function factIds(array $result): array
    {
        return array_column($result['rows'], 'factId');
    }
}
