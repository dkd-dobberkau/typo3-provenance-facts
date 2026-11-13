<?php

declare(strict_types=1);

use CmsProvenance\ProvenanceFacts\Backend\CreateFactController;

return [
    'provenance_facts_create' => [
        'path' => '/provenance-facts/create',
        'target' => CreateFactController::class . '::createAction',
        'methods' => ['POST'],
    ],
];
