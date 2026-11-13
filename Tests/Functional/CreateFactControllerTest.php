<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional;

use CmsProvenance\ProvenanceFacts\Backend\CreateFactController;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class CreateFactControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FactsScenario.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function createsAFactForTheSelectedText(): void
    {
        $response = $this->create(['quote' => 'Revenue grew']);
        $result = json_decode((string)$response->getBody(), true);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $fact = BackendUtility::getRecord('tx_provenancefacts_fact', $result['uid']);
        self::assertSame('growth-2025', $fact['fact_id']);
        self::assertSame('Revenue grew', $fact['quote']);
        self::assertSame(1, (int)$fact['content_uid']);
        self::assertSame(1, (int)$fact['pid']);
        self::assertSame('description', $fact['predicate']);
        self::assertSame('measured', $fact['confidence']);
    }

    #[Test]
    public function takenFactIdIsMadeUniqueAndReported(): void
    {
        $result = json_decode((string)$this->create(['factId' => 'revenue-2025'])->getBody(), true);

        self::assertNotSame('revenue-2025', $result['factId']);
        self::assertStringStartsWith('revenue-2025', $result['factId']);
    }

    #[Test]
    public function incompleteInputIsRejected(): void
    {
        $response = $this->create(['quote' => '', 'subject' => '']);

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('quote', json_decode((string)$response->getBody(), true)['errors']);
    }

    #[Test]
    public function unknownContentElementIsNotFound(): void
    {
        self::assertSame(404, $this->create(['contentUid' => 999])->getStatusCode());
    }

    #[Test]
    public function savingTheStillOpenFormKeepsTheNewFact(): void
    {
        $result = json_decode((string)$this->create([])->getBody(), true);

        // The editor's form was loaded before the fact existed and submits the old child list.
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['tt_content' => [1 => ['bodytext' => '<p>Changed</p>', 'provenance_facts' => '1,2']]], []);
        $dataHandler->process_datamap();

        $fact = BackendUtility::getRecord('tx_provenancefacts_fact', $result['uid']);
        self::assertIsArray($fact);
        self::assertSame(1, (int)$fact['content_uid']);
    }

    private function create(array $override): ResponseInterface
    {
        $body = array_merge([
            'table' => 'tt_content',
            'contentUid' => 1,
            'quote' => 'Revenue grew',
            'factId' => 'growth-2025',
            'subject' => 'https://example.org/company',
            'predicate' => 'description',
            'objectLiteral' => 'grew',
            'confidence' => 'measured',
        ], $override);
        $request = (new ServerRequest('http://localhost/typo3/ajax/provenance-facts/create', 'POST'))
            ->withParsedBody($body);

        return $this->get(CreateFactController::class)->createAction($request);
    }
}
