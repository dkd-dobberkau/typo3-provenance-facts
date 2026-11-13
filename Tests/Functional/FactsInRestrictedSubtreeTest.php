<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Access restrictions and hiding that a parent page extends to its subpages
 * must also keep the facts of those subpages off the public endpoint.
 */
final class FactsInRestrictedSubtreeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RestrictedSubtree.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance_facts/Tests/Functional/Fixtures/page.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function frontendDoesNotDeliverTheSubpagesAnonymously(): void
    {
        self::assertNotSame(200, $this->statusOf('http://localhost/members/minutes'));
        self::assertNotSame(200, $this->statusOf('http://localhost/embargoed/results'));
    }

    #[Test]
    public function factBelowAPageRestrictedToAGroupIsNotPublished(): void
    {
        self::assertSame(404, $this->statusOf('http://localhost/.well-known/content-provenance/facts/board-decision'));
    }

    #[Test]
    public function factBelowAHiddenPageIsNotPublished(): void
    {
        self::assertSame(404, $this->statusOf('http://localhost/.well-known/content-provenance/facts/embargoed-result'));
    }

    private function statusOf(string $url): int
    {
        return $this->executeFrontendSubRequest(new InternalRequest($url))->getStatusCode();
    }
}
