<?php

declare(strict_types=1);

$ll = 'LLL:EXT:provenance_facts/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_provenancefacts_fact',
        'label' => 'fact_id',
        'label_alt' => 'quote',
        'label_alt_force' => true,
        'sortby' => 'sorting',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        // Inline child of content elements, stored next to them
        'versioningWS' => true,
        'security' => ['ignorePageTypeRestriction' => true],
        'hideTable' => true,
        'typeicon_classes' => ['default' => 'provenance-fact'],
    ],
    'columns' => [
        'content_uid' => [
            'config' => ['type' => 'passthrough'],
        ],
        'hidden' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],
        'fact_id' => [
            'label' => $ll . 'tx_provenancefacts_fact.fact_id',
            'description' => $ll . 'tx_provenancefacts_fact.fact_id.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 64,
                'eval' => 'trim,alphanum_x,lower,unique',
                'required' => true,
            ],
        ],
        'quote' => [
            'label' => $ll . 'tx_provenancefacts_fact.quote',
            'description' => $ll . 'tx_provenancefacts_fact.quote.description',
            'config' => [
                'type' => 'text',
                'rows' => 2,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'subject' => [
            'label' => $ll . 'tx_provenancefacts_fact.subject',
            'description' => $ll . 'tx_provenancefacts_fact.subject.description',
            'config' => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'predicate' => [
            'label' => $ll . 'tx_provenancefacts_fact.predicate',
            'description' => $ll . 'tx_provenancefacts_fact.predicate.description',
            'config' => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'object_literal' => [
            'label' => $ll . 'tx_provenancefacts_fact.object_literal',
            'config' => ['type' => 'input', 'size' => 60, 'eval' => 'trim'],
        ],
        'object_uri' => [
            'label' => $ll . 'tx_provenancefacts_fact.object_uri',
            'config' => ['type' => 'link', 'allowedTypes' => ['url']],
        ],
        'source_url' => [
            'label' => $ll . 'tx_provenancefacts_fact.source_url',
            'config' => ['type' => 'link', 'allowedTypes' => ['url']],
        ],
        'confidence' => [
            'label' => $ll . 'tx_provenancefacts_fact.confidence',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'confidence.asserted', 'value' => 'asserted'],
                    ['label' => $ll . 'confidence.estimated', 'value' => 'estimated'],
                    ['label' => $ll . 'confidence.measured', 'value' => 'measured'],
                ],
                'default' => 'asserted',
            ],
        ],
        'valid_from' => [
            'label' => $ll . 'tx_provenancefacts_fact.valid_from',
            'config' => ['type' => 'datetime', 'format' => 'date'],
        ],
        'valid_until' => [
            'label' => $ll . 'tx_provenancefacts_fact.valid_until',
            'config' => ['type' => 'datetime', 'format' => 'date'],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'fact_id, quote,
                --div--;' . $ll . 'tab.statement, subject, predicate, object_literal, object_uri,
                --div--;' . $ll . 'tab.evidence, source_url, confidence, valid_from, valid_until,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ],
    ],
];
