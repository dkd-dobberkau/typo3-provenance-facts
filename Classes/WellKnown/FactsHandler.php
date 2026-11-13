<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\WellKnown;

use CmsProvenance\Provenance\WellKnown\WellKnownHandlerInterface;
use CmsProvenance\Provenance\WellKnown\WellKnownRouter;
use CmsProvenance\ProvenanceFacts\Domain\Repository\FactRepository;
use CmsProvenance\ProvenanceFacts\Presentation\FactPresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * /.well-known/content-provenance/facts/{factId}: one fact as a W3C Web
 * Annotation, answered only by the site the fact is published on.
 */
final readonly class FactsHandler implements WellKnownHandlerInterface
{
    public const SUB_PATH = 'facts';
    private const CONTENT_TYPE = 'application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"';

    public function __construct(
        private FactRepository $factRepository,
        private FactPresenter $factPresenter,
        private SiteFinder $siteFinder,
    ) {}

    public function getSubPath(): string
    {
        return self::SUB_PATH;
    }

    public function handle(ServerRequestInterface $request, string $remainder): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $found = preg_match('/^[a-z0-9_-]+$/', $remainder) === 1 ? $this->factRepository->findPublishedById($remainder) : null;
        if (!$site instanceof Site || $found === null || !$this->belongsTo($found['pageUid'], $site)) {
            return new JsonResponse(['error' => 'unknown fact'], 404);
        }

        $siteUrl = rtrim((string)$site->getBase(), '/');
        $annotation = $this->factPresenter->annotation(
            $found['fact'],
            (string)$site->getRouter()->generateUri($found['pageUid']),
            $siteUrl . WellKnownRouter::BASE_PATH . '/' . self::SUB_PATH . '/' . $found['fact']->factId
        );

        return (new JsonResponse($annotation, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->withHeader('Content-Type', self::CONTENT_TYPE)
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    private function belongsTo(int $pageUid, Site $site): bool
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageUid)->getIdentifier() === $site->getIdentifier();
        } catch (SiteNotFoundException) {
            return false;
        }
    }
}
