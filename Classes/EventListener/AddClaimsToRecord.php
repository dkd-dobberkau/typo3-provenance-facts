<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\EventListener;

use CmsProvenance\Provenance\Event\BeforeJsonLdEmittedEvent;
use CmsProvenance\Provenance\Service\AbsoluteUrlResolver;
use CmsProvenance\ProvenanceFacts\Domain\Repository\FactRepository;
use CmsProvenance\ProvenanceFacts\Presentation\FactPresenter;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Adds the page's facts as "claim" to the provenance record. The signing
 * extension signs the record afterwards, so the claims are covered too.
 */
#[AsEventListener(identifier: 'provenance-facts/add-claims')]
final readonly class AddClaimsToRecord
{
    private const CITABLE_LEVEL = 4;

    public function __construct(
        private FactRepository $factRepository,
        private FactPresenter $factPresenter,
        private AbsoluteUrlResolver $urlResolver,
    ) {}

    public function __invoke(BeforeJsonLdEmittedEvent $event): void
    {
        $request = $event->getRequest();
        $pageInformation = $request->getAttribute('frontend.page.information');
        $site = $request->getAttribute('site');
        if (!$pageInformation instanceof PageInformation || !$site instanceof Site) {
            return;
        }

        $facts = $this->factRepository->findByPage($pageInformation->getId());
        $jsonLd = $event->getJsonLd();
        if ($facts === [] || $jsonLd === []) {
            return;
        }

        $pageUrl = $this->urlResolver->pageUrl($site, $pageInformation->getId(), $request);
        $jsonLd['claim'] = array_map(fn($fact): array => $this->factPresenter->claim($fact, $pageUrl), $facts);
        // L4 Citable needs Layers 1 to 4: signatures and the audit endpoint as well.
        if (ExtensionManagementUtility::isLoaded('provenance_signing') && ExtensionManagementUtility::isLoaded('provenance_audit')) {
            $jsonLd['cpsConformance'] = max(self::CITABLE_LEVEL, (int)($jsonLd['cpsConformance'] ?? 0));
        }
        $event->setJsonLd($jsonLd);
    }
}
