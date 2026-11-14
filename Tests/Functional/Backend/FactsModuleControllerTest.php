<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional\Backend;

use CmsProvenance\ProvenanceFacts\Backend\Controller\FactsModuleController;
use CmsProvenance\ProvenanceFacts\Event\ModifyFactOverviewRowEvent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FactsModuleControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FactOverview.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Editors.csv');
        // Every page readable and editable for everybody; table permissions decide.
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->update('pages', ['perms_everybody' => 31], ['pid' => 0]);
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->update('pages', ['perms_everybody' => 31], ['pid' => 1]);
    }

    #[Test]
    public function adminSeesFactsWithShortUrisAndEditLinks(): void
    {
        $body = $this->render(1, ['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('opened in 1683', $body);
        self::assertStringContainsString('>Q636400</a>', $body);
        self::assertStringContainsString('7 facts', $body);
        self::assertStringContainsString('record/edit', $body);
    }

    #[Test]
    public function searchNarrowsTheList(): void
    {
        $body = $this->render(1, ['id' => 1, 'depth' => 1, 'search' => 'Q471406']);

        self::assertStringContainsString('draft-fact', $body);
        self::assertStringNotContainsString('opened in 1683', $body);
    }

    #[Test]
    public function userWithoutAccessToFactsSeesNone(): void
    {
        $body = $this->render(2, ['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('You may not view facts.', $body);
        self::assertStringNotContainsString('opened in 1683', $body);
    }

    #[Test]
    public function readerGetsNoEditLink(): void
    {
        $body = $this->render(3, ['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('opened in 1683', $body);
        self::assertStringNotContainsString('record/edit', $body);
    }

    #[Test]
    public function editorGetsEditLinks(): void
    {
        self::assertStringContainsString('record/edit', $this->render(4, ['id' => 1, 'depth' => 1]));
    }

    #[Test]
    public function onlyWebAddressesBecomeLinks(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_provenancefacts_fact')
            ->update('tx_provenancefacts_fact', ['subject' => 'javascript:alert(1)', 'source_url' => 'javascript:alert(2)'], ['uid' => 1]);

        $body = $this->render(1, ['id' => 1, 'depth' => 1]);

        self::assertStringNotContainsString('href="javascript:', $body);
        self::assertStringContainsString('javascript:alert(1)', $body);
    }

    #[Test]
    public function listenersCanAddLinkColumns(): void
    {
        $this->getContainer()->set('provenance.test.fact-link-listener', new class () {
            public function __invoke(ModifyFactOverviewRowEvent $event): void
            {
                $row = $event->getRow();
                $row['extra']['Check'] = ['label' => 'Wikidata', 'url' => 'https://www.wikidata.org/wiki/' . $row['factId']];
                $event->setRow($row);
            }
        });
        $this->get(ListenerProvider::class)->addListener(ModifyFactOverviewRowEvent::class, 'provenance.test.fact-link-listener');

        $body = $this->render(1, ['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('<th>Check</th>', $body);
        self::assertStringContainsString('href="https://www.wikidata.org/wiki/ashmolean-opened"', $body);
    }

    /**
     * @param array<string, int|string> $query
     */
    private function render(int $userUid, array $query): string
    {
        $user = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($user);
        $module = $this->get(ModuleProvider::class)->getModule('provenance_facts', $user);
        $request = (new ServerRequest('https://example.org/typo3/module/web/provenance/facts', 'GET'))
            ->withQueryParams($query)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', $module)
            ->withAttribute('moduleData', ModuleData::createFromModule($module, []))
            ->withAttribute('route', new Route('/module/web/provenance/facts', ['packageName' => 'cms-provenance/provenance-facts', '_identifier' => 'provenance_facts']));
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $response = $this->get(FactsModuleController::class)->handleRequest($request);
        self::assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }
}
