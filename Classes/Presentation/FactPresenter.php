<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Presentation;

use CmsProvenance\ProvenanceFacts\Domain\Model\Fact;

/**
 * Representations of a fact: as an entry of the record's "claim" list and as
 * a W3C Web Annotation for the audit endpoint.
 */
final class FactPresenter
{
    private const DEFAULT_VOCABULARY = 'https://schema.org/';

    /**
     * @return array<string, mixed>
     */
    public function claim(Fact $fact, string $pageUrl): array
    {
        return array_filter([
            '@type' => 'Claim',
            '@id' => $this->factUri($fact, $pageUrl),
            'text' => $fact->quote,
            'subject' => $fact->subject,
            'predicate' => $this->predicateUri($fact->predicate),
            'object' => $fact->objectUri !== null ? ['@id' => $fact->objectUri] : $fact->objectLiteral,
            'evidence' => $fact->sourceUrl,
            'confidence' => $fact->confidence,
            'validFrom' => $this->date($fact->validFrom),
            'validUntil' => $this->date($fact->validUntil),
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function annotation(Fact $fact, string $pageUrl, string $annotationUrl): array
    {
        $body = $this->claim($fact, $pageUrl);
        unset($body['@type'], $body['@id'], $body['text']);

        return [
            '@context' => ['http://www.w3.org/ns/anno.jsonld', 'https://cms-provenance.org/ns/v1'],
            'id' => $annotationUrl,
            'type' => 'Annotation',
            'motivation' => 'describing',
            'created' => gmdate('Y-m-d\TH:i:s\Z', $fact->createdAt),
            'body' => ['id' => $this->factUri($fact, $pageUrl)] + $body,
            'target' => [
                'source' => $pageUrl,
                'selector' => [
                    ['type' => 'FragmentSelector', 'conformsTo' => 'http://tools.ietf.org/rfc/rfc3986', 'value' => $fact->fragment()],
                    ['type' => 'TextQuoteSelector', 'exact' => $fact->quote],
                ],
            ],
        ];
    }

    private function factUri(Fact $fact, string $pageUrl): string
    {
        return strtok($pageUrl, '#') . '#' . $fact->fragment();
    }

    private function predicateUri(string $predicate): string
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $predicate) === 1 ? $predicate : self::DEFAULT_VOCABULARY . $predicate;
    }

    /**
     * A date field holds midnight in the server time zone; formatting it in
     * UTC would give the previous day east of Greenwich.
     */
    private function date(?int $timestamp): ?string
    {
        return $timestamp !== null ? date('Y-m-d', $timestamp) : null;
    }
}
