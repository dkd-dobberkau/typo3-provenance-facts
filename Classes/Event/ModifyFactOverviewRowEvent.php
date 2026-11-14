<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Event;

/**
 * Dispatched for every row of the "Facts" overview in the Provenance module.
 * Listeners add values under the row's "extra" key, for example a Wikidata
 * status; the template shows each extra value as a column.
 */
final class ModifyFactOverviewRowEvent
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(private array $row) {}

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
    }
}
