<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'ITX Categories',
    'description' => 'Dedicated backend module for browsing sys_category records as a tree, with language filter and record actions.',
    'category' => 'module',
    'author' => 'it.x informationssysteme gmbh',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
    ],
];
