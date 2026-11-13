<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Domain\Model;

/**
 * One addressable claim inside a content element (specification Layer 3).
 * Dates are Unix timestamps; null means "not set".
 */
final readonly class Fact
{
    public function __construct(
        public string $factId,
        public string $quote,
        public string $subject,
        public string $predicate,
        public ?string $objectLiteral,
        public ?string $objectUri,
        public ?string $sourceUrl,
        public string $confidence,
        public ?int $validFrom,
        public ?int $validUntil,
        public int $createdAt,
    ) {}

    public function fragment(): string
    {
        return 'fact-' . $this->factId;
    }
}
