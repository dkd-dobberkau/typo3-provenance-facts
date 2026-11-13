<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'provenance_facts' => [
        'label' => 'locallang_db.xlft_content.provenance_facts',
        'description' => 'locallang_db.xlft_content.provenance_facts.description',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_provenancefacts_fact',
            'foreign_field' => 'content_uid',
            'foreign_sortby' => 'sorting',
            'appearance' => [
                'collapseAll' => true,
                'levelLinksPosition' => 'bottom',
                'useSortable' => true,
            ],
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;locallang_db.xlfab.facts, provenance_facts'
);
