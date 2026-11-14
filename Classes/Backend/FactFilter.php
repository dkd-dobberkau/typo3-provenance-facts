<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Backend;

/**
 * What the editor narrowed the facts overview to; empty strings mean "all".
 */
final readonly class FactFilter
{
    public function __construct(
        public string $search = '',
        public string $predicate = '',
        public string $confidence = '',
    ) {}
}
