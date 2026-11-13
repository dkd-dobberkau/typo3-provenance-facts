<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A site may use a relative base such as "/"; claims and annotations must
 * still carry absolute URLs, taking scheme and host from the request.
 */
final class FactsWithRelativeBaseTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FactsScenario.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance_facts/Tests/Functional/Fixtures/page.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, '/');
    }

    #[Test]
    public function claimIdentifierIsAbsolute(): void
    {
        $html = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/'))->getBody();
        self::assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches));
        $record = json_decode($matches[1], false, 512, JSON_THROW_ON_ERROR);

        self::assertSame('http://localhost/#fact-revenue-2025', $record->claim[0]->{'@id'});
    }

    #[Test]
    public function annotationUrlsAreAbsolute(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/.well-known/content-provenance/facts/revenue-2025'));
        $annotation = json_decode((string)$response->getBody(), false, 512, JSON_THROW_ON_ERROR);

        self::assertSame('http://localhost/.well-known/content-provenance/facts/revenue-2025', $annotation->id);
        self::assertSame('http://localhost/', $annotation->target->source);
        self::assertSame('http://localhost/#fact-revenue-2025', $annotation->body->id);
    }
}
