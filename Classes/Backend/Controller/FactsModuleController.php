<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Backend\Controller;

use CmsProvenance\Provenance\Backend\Controller\PagesModuleController;
use CmsProvenance\Provenance\Backend\PageSubtree;
use CmsProvenance\ProvenanceFacts\Backend\FactFilter;
use CmsProvenance\ProvenanceFacts\Backend\FactOverview;
use CmsProvenance\ProvenanceFacts\Backend\UriLabel;
use CmsProvenance\ProvenanceFacts\Event\BeforeFactOverviewRenderedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Submodule "Facts" of the Provenance module: all facts of the pages below
 * the selected one, searchable and filterable. Read-only; editing opens the
 * content element.
 */
#[AsController]
final readonly class FactsModuleController
{
    private const CONFIDENCE = ['asserted', 'estimated', 'measured'];
    private const MANUAL = 'https://docs.typo3.org/p/cms-provenance/provenance/main/en-us/Editors/Index.html#editors-facts';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageSubtree $pageSubtree,
        private FactOverview $factOverview,
        private UriBuilder $uriBuilder,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $user = $GLOBALS['BE_USER'];
        $module = $request->getAttribute('module');
        $query = $request->getQueryParams();
        $pageId = (int)($query['id'] ?? 0);
        $depth = PagesModuleController::depth($request, $user);
        $filter = $this->filter($request, $user);
        $pageRecord = $pageId > 0 ? (BackendUtility::readPageAccess($pageId, $user->getPagePermsClause(Permission::PAGE_SHOW)) ?: []) : [];
        $mayView = $user->check('tables_select', 'tx_provenancefacts_fact');

        $pageIds = $pageRecord !== [] && $mayView ? $this->pageSubtree->pageIds($pageId, $depth, $user) : [];
        $result = $this->factOverview->find($pageIds, $filter, (int)($query['page'] ?? 1));
        $moduleUrl = fn(array $parameters = []): string => (string)$this->uriBuilder->buildUriFromRoute($module->getIdentifier(), ['id' => $pageId] + $parameters);

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($GLOBALS['LANG']->sL($module->getTitle()), (string)($pageRecord['title'] ?? ''));
        $view->makeDocHeaderModuleMenu(['id' => $pageId]);
        PagesModuleController::setPageInformation($view, $pageRecord);
        $view->assignMultiple([
            'pageId' => $pageId,
            'pageSelected' => $pageRecord !== [],
            'mayView' => $mayView,
            'depth' => $depth,
            'depths' => PagesModuleController::DEPTHS,
            'filter' => $filter,
            'filtered' => $filter != new FactFilter(),
            'predicates' => $this->factOverview->predicates($pageIds),
            'confidences' => self::CONFIDENCE,
            'inWorkspace' => $user->workspace !== 0,
            'result' => $result,
            'rows' => $this->forDisplay($result['rows'], $user, (string)$request->getUri()),
            'moduleUrl' => $moduleUrl(),
            'resetUrl' => $moduleUrl(['search' => '', 'predicate' => '', 'confidence' => '']),
            'previousUrl' => $result['page'] > 1 ? $moduleUrl(['page' => $result['page'] - 1]) : '',
            'nextUrl' => $result['page'] < $result['pages'] ? $moduleUrl(['page' => $result['page'] + 1]) : '',
            'manualUrl' => self::MANUAL,
        ]);
        $this->eventDispatcher->dispatch(new BeforeFactOverviewRenderedEvent($view, $pageId, $request));

        return $view->renderResponse('FactsModule');
    }

    /**
     * Filters from the request, else those stored for the user.
     */
    private function filter(ServerRequestInterface $request, BackendUserAuthentication $user): FactFilter
    {
        $moduleData = $request->getAttribute('moduleData');
        $query = $request->getQueryParams();
        $changed = false;
        foreach (['search', 'predicate', 'confidence'] as $key) {
            if (isset($query[$key]) && is_string($query[$key])) {
                $moduleData->set($key, trim(mb_substr($query[$key], 0, 200)));
                $changed = true;
            }
        }
        if ($changed) {
            $user->pushModuleData($request->getAttribute('module')->getIdentifier(), $moduleData->toArray());
        }
        $confidence = (string)$moduleData->get('confidence', '');

        return new FactFilter(
            (string)$moduleData->get('search', ''),
            (string)$moduleData->get('predicate', ''),
            in_array($confidence, self::CONFIDENCE, true) ? $confidence : ''
        );
    }

    /**
     * Short labels for URIs and the edit link, where the user may edit.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function forDisplay(array $rows, BackendUserAuthentication $user, string $returnUrl): array
    {
        $mayModifyContent = $user->check('tables_modify', 'tt_content');
        $editablePages = [];
        foreach ($rows as &$row) {
            $row['subjectLabel'] = UriLabel::short($row['subject']);
            $row['objectUriLabel'] = UriLabel::short($row['objectUri']);
            $row['sourceLabel'] = (string)parse_url($row['sourceUrl'], PHP_URL_HOST) ?: $row['sourceUrl'];
            // Only web addresses become links; editors could have entered anything.
            $row['subjectLink'] = self::webAddress($row['subject']);
            $row['objectUriLink'] = self::webAddress($row['objectUri']);
            $row['sourceLink'] = self::webAddress($row['sourceUrl']);
            $editablePages[$row['pageUid']] ??= $mayModifyContent
                && $user->doesUserHaveAccess(BackendUtility::getRecord('pages', $row['pageUid']) ?? [], Permission::CONTENT_EDIT);
            $row['editUrl'] = $editablePages[$row['pageUid']]
                ? (string)$this->uriBuilder->buildUriFromRoute('record_edit', ['edit' => ['tt_content' => [$row['contentUid'] => 'edit']], 'returnUrl' => $returnUrl])
                : '';
            $row['pageUrl'] = (string)$this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $row['pageUid']]);
        }

        return $rows;
    }

    private static function webAddress(string $value): string
    {
        return preg_match('#^https?://#i', $value) === 1 ? $value : '';
    }
}
