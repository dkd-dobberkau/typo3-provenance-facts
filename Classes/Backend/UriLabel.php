<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Backend;

/**
 * Short labels for subjects, predicates and values in the facts overview:
 * a Wikidata item or property as its id, another URL as host and path,
 * anything else as it is.
 */
final class UriLabel
{
    public static function short(string $value): string
    {
        if (preg_match('#^https?://www\.wikidata\.org/(?:entity|wiki|prop/direct)/([PQ]\d+)$#', $value, $match) === 1) {
            return $match[1];
        }
        if (preg_match('#^https?://#', $value) !== 1) {
            return $value;
        }
        $parts = parse_url($value);

        return rtrim(($parts['host'] ?? '') . ($parts['path'] ?? ''), '/');
    }
}
