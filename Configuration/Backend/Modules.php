<?php

declare(strict_types=1);

use CmsProvenance\ProvenanceFacts\Backend\Controller\FactsModuleController;

return [
    'provenance_facts' => [
        'parent' => 'provenance',
        'access' => 'user',
        'path' => '/module/web/provenance/facts',
        'iconIdentifier' => 'module-provenance',
        'labels' => 'LLL:EXT:provenance_facts/Resources/Private/Language/locallang_mod_facts.xlf',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => FactsModuleController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'depth' => 1,
            'search' => '',
            'predicate' => '',
            'confidence' => '',
        ],
    ],
];
