<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Unit\Backend;

use CmsProvenance\ProvenanceFacts\Backend\UriLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class UriLabelTest extends UnitTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function values(): array
    {
        return [
            'Wikidata entity' => ['http://www.wikidata.org/entity/Q636400', 'Q636400'],
            'Wikidata page' => ['https://www.wikidata.org/wiki/Q42', 'Q42'],
            'Wikidata property' => ['http://www.wikidata.org/prop/direct/P1082', 'P1082'],
            'other URL' => ['https://en.wikipedia.org/wiki/Louvre', 'en.wikipedia.org/wiki/Louvre'],
            'host only' => ['https://example.org/', 'example.org'],
            'plain text' => ['Daughter of Nabonidus', 'Daughter of Nabonidus'],
            'empty' => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('values')]
    public function shortensUris(string $value, string $label): void
    {
        self::assertSame($label, UriLabel::short($value));
    }
}
