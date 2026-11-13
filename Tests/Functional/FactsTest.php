<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FactsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FactsScenario.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance_facts/Tests/Functional/Fixtures/page.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function quotedTextIsAnchoredOnThePage(): void
    {
        $html = $this->body($this->request('http://localhost/'));

        self::assertStringContainsString(
            'Revenue grew to <span id="fact-revenue-2025" class="cps-fact" itemscope itemtype="https://schema.org/Claim"><span itemprop="text">12.4 million EUR</span></span> in 2025.',
            $html
        );
        self::assertStringNotContainsString('fact-draft-fact', $html);
    }

    #[Test]
    public function recordListsTheVisibleFactsAsClaims(): void
    {
        $record = $this->record($this->body($this->request('http://localhost/')));

        self::assertCount(1, $record->claim);
        self::assertSame('http://localhost/#fact-revenue-2025', $record->claim[0]->{'@id'});
        self::assertSame('https://schema.org/revenue', $record->claim[0]->predicate);
        self::assertSame('12400000', $record->claim[0]->object);
        self::assertSame(2, $record->cpsConformance, 'facts alone do not raise the level; L4 needs a signature as well');
    }

    #[Test]
    public function factIsPublishedAsWebAnnotation(): void
    {
        $response = $this->request('http://localhost/.well-known/content-provenance/facts/revenue-2025');
        $annotation = json_decode($this->body($response), false, 512, JSON_THROW_ON_ERROR);

        self::assertStringStartsWith('application/ld+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('http://localhost/.well-known/content-provenance/facts/revenue-2025', $annotation->id);
        self::assertSame('Annotation', $annotation->type);
        self::assertSame('http://localhost/', $annotation->target->source);
        self::assertSame('fact-revenue-2025', $annotation->target->selector[0]->value);
        self::assertSame('12.4 million EUR', $annotation->target->selector[1]->exact);
        self::assertSame('https://example.org/annual-report.pdf', $annotation->body->evidence);
    }

    #[Test]
    public function hiddenFactsAndFactsOnHiddenPagesAreNotPublished(): void
    {
        self::assertSame(404, $this->request('http://localhost/.well-known/content-provenance/facts/draft-fact')->getStatusCode());
        self::assertSame(404, $this->request('http://localhost/.well-known/content-provenance/facts/secret')->getStatusCode());
        self::assertSame(404, $this->request('http://localhost/.well-known/content-provenance/facts/unknown')->getStatusCode());
    }

    #[Test]
    public function factIdsAreKeptUnique(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_provenancefacts_fact' => [
                'NEW1' => ['pid' => 1, 'content_uid' => 1, 'fact_id' => 'Revenue-2025', 'quote' => 'x', 'subject' => 'https://example.org', 'predicate' => 'name'],
            ],
        ], []);
        $dataHandler->process_datamap();

        $created = BackendUtility::getRecord('tx_provenancefacts_fact', (int)$dataHandler->substNEWwithIDs['NEW1']);
        self::assertNotSame('revenue-2025', $created['fact_id']);
        self::assertStringStartsWith('revenue-2025', $created['fact_id']);
    }

    private function request(string $url): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest($url));
    }

    private function body(ResponseInterface $response): string
    {
        return (string)$response->getBody();
    }

    private function record(string $html): \stdClass
    {
        self::assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches));
        return json_decode($matches[1], false, 512, JSON_THROW_ON_ERROR);
    }
}
