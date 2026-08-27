<?php

use Itx\Categories\Controller\CategoryTreeModuleController;

/**
 * Definitions for modules provided by EXT:edit_categories
 */
return [
    'web_CategoryTree' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'path' => '/module/web/CategoryTree',
        'iconIdentifier' => 'module-categorytree',
        'labels' => 'LLL:EXT:edit_categories/Resources/Private/Language/locallang_mod_categorytree.xlf',
        'routes' => [
            '_default' => [
                'target' => CategoryTreeModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
