<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Event;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;

/**
 * Dispatched before the "Facts" overview is rendered, for example to add a
 * button to the doc header or variables to the view (a later fact-suggestion
 * feature would add its action here).
 */
final readonly class BeforeFactOverviewRenderedEvent
{
    public function __construct(
        private ModuleTemplate $view,
        private int $pageId,
        private ServerRequestInterface $request,
    ) {}

    public function getView(): ModuleTemplate
    {
        return $this->view;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
