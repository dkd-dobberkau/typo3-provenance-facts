<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceFacts\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Every LLL reference of the provenance extensions in the final TCA must
 * resolve to a text, so editors never see raw label keys.
 */
final class LabelsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    #[Test]
    public function allProvenanceLabelsResolve(): void
    {
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        $references = [];
        array_walk_recursive($GLOBALS['TCA'], static function (mixed $value) use (&$references): void {
            if (!is_string($value)) {
                return;
            }
            preg_match_all('/LLL:EXT:provenance(?:_facts)?\/[^,;\s\']+/', $value, $matches);
            array_push($references, ...$matches[0]);
        });
        array_walk_recursive($GLOBALS['TCA'], static function (mixed $value): void {
            if (is_string($value)) {
                self::assertDoesNotMatchRegularExpression('/(^|;)locallang_db\.xlf/', $value, 'Label without LLL:EXT: prefix');
            }
        });

        self::assertNotEmpty($references);
        foreach (array_unique($references) as $reference) {
            self::assertNotSame('', $languageService->sL($reference), $reference . ' does not resolve');
        }
    }
}
