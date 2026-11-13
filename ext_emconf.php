<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Provenance: Facts',
    'description' => 'Addressable facts within content, exposed as Microdata and W3C Web Annotations.',
    'category' => 'fe',
    'state' => 'experimental',
    'version' => '0.0.0',
    'constraints' => [
        'depends' => [
        'typo3' => '13.4.0-14.99.99',
        'php' => '8.2.0-8.4.99',
        'provenance' => '0.1.0-0.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
