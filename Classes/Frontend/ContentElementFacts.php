<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Frontend;

use CmsProvenance\ProvenanceFacts\Domain\Repository\FactRepository;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * postUserFunc on tt_content.stdWrap (see the facts site set): anchors the
 * facts of each rendered content element. Runs inside the cached rendering.
 */
final class ContentElementFacts
{
    private ?ContentObjectRenderer $contentObjectRenderer = null;

    public function __construct(
        private readonly FactRepository $factRepository,
        private readonly FactAnchorInserter $anchorInserter,
    ) {}

    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    // Required for userFunc callbacks since TYPO3 14; ignored by TYPO3 13.
    #[AsAllowedCallable]
    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        $data = $this->contentObjectRenderer?->data ?? [];
        // Facts belong to the default-language element; translations point to it.
        $contentUid = (int)($data['l18n_parent'] ?? 0) ?: (int)($data['uid'] ?? 0);
        if ($contentUid === 0) {
            return $content;
        }

        return $this->anchorInserter->insert($content, $this->factRepository->findByContentElement($contentUid));
    }
}
