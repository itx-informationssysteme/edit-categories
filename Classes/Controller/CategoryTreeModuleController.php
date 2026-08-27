<?php

declare(strict_types=1);

namespace Itx\Categories\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Dedicated backend module "Categories" that renders sys_category records as a real
 * tree (like the page tree), instead of the flat Web>List view. Also allows hiding
 * translated (non-default-language) category records via a toggle.
 */
#[AsController]
class CategoryTreeModuleController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly UriBuilder $uriBuilder,
        protected readonly ConnectionPool $connectionPool,
        protected readonly SiteFinder $siteFinder,
        protected readonly PageRenderer $pageRenderer,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:edit_categories/Resources/Private/Language/locallang_mod_categorytree.xlf:mlang_tabs_tab')
        );
        $this->pageRenderer->addJsFooterFile('EXT:edit_categories/Resources/Public/JavaScript/scroll-restore.js');

        $queryParams = $request->getQueryParams();
        $showTranslations = (bool)($queryParams['showTranslations'] ?? false);

        $rows = $this->fetchAllCategories($showTranslations);
        $moduleUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_CategoryTree',
            $showTranslations ? ['showTranslations' => '1'] : []
        );
        $tree = $this->buildTree($rows, $moduleUrl);

        $view->assignMultiple([
            'tree' => $tree,
            'showTranslations' => $showTranslations,
            'editUrl' => (string)$this->uriBuilder->buildUriFromRoute('record_edit'),
            'newRootLink' => (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_category' => [0 => 'new']],
                'returnUrl' => $moduleUrl,
            ]),
            'moduleUrl' => (string)$this->uriBuilder->buildUriFromRoute('web_CategoryTree'),
        ]);

        return $view->renderResponse('CategoryTree/Index');
    }

    /**
     * Fetches all sys_category rows (default language + translations), each row
     * enriched with the icon markup and edit/delete/move/localize links needed by
     * the template. Translations are always fetched (needed for the "Localization"
     * column and, when toggled on, for display as their own rows).
     */
    protected function fetchAllCategories(bool $showTranslations = false): array
    {
        $languages = $this->getLanguagesByUid();

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->select('uid', 'pid', 'parent', 'title', 'hidden', 'sys_language_uid', 'l10n_parent')
            ->from('sys_category')
            ->orderBy('sorting', 'ASC');

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        $moduleUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_CategoryTree',
            $showTranslations ? ['showTranslations' => '1'] : []
        );

        foreach ($rows as &$row) {
            $row['icon'] = $this->iconFactory->getIconForRecord('sys_category', $row, IconSize::SMALL)->render();
            $languageUid = (int)$row['sys_language_uid'];
            $row['languageTitle'] = $languages[$languageUid]['title'] ?? null;
            $row['languageFlagIdentifier'] = $languages[$languageUid]['flagIdentifier'] ?? null;
            $row['editLink'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_category' => [$row['uid'] => 'edit']],
                'returnUrl' => $moduleUrl,
            ]);
            $row['newSubcategoryLink'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => ['sys_category' => [$row['uid'] => 'new']],
                'defVals' => ['sys_category' => ['parent' => $row['uid']]],
                'returnUrl' => $moduleUrl,
            ]);
            $row['toggleHiddenLink'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                'data' => [
                    'sys_category' => [
                        $row['uid'] => [
                            'hidden' => $row['hidden'] ? 0 : 1,
                        ],
                    ],
                ],
                'redirect' => $moduleUrl,
            ]);
            $row['deleteLink'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                'cmd' => [
                    'sys_category' => [
                        $row['uid'] => [
                            'delete' => 1,
                        ],
                    ],
                ],
                'redirect' => $moduleUrl,
            ]);
        }
        unset($row);

        return $rows;
    }

    /**
     * Builds a nested tree structure (children keyed under 'children') from a flat
     * list of category rows, following the "parent" relation (comma separated uid list).
     * Also attaches move-up/move-down links (based on sibling order within each level)
     * and, for default-language rows, a "translations" list (existing translations)
     * plus "localizeLinks" (missing languages that can still be localized to).
     */
    protected function buildTree(array $rows, string $moduleUrl): array
    {
        $byUid = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byUid[$row['uid']] = $row;
        }

        $translationsByParent = [];
        foreach ($byUid as $row) {
            $l10nParent = (int)$row['l10n_parent'];
            if ($l10nParent > 0) {
                $translationsByParent[$l10nParent][] = $row;
            }
        }

        $languages = $this->getLanguagesByUid();

        $roots = [];
        foreach ($byUid as $uid => $row) {
            if ((int)$row['sys_language_uid'] !== 0) {
                // Translations are attached to their default-language parent below,
                // not placed as their own tree nodes.
                continue;
            }

            $existingLanguageUids = array_map(
                static fn(array $translation): int => (int)$translation['sys_language_uid'],
                $translationsByParent[$uid] ?? []
            );
            $localizeLinks = [];
            foreach ($languages as $languageUid => $language) {
                if ($languageUid === 0 || in_array($languageUid, $existingLanguageUids, true)) {
                    continue;
                }
                $localizeLinks[] = [
                    'languageUid' => $languageUid,
                    'languageTitle' => $language['title'],
                    'languageFlagIdentifier' => $language['flagIdentifier'],
                    'link' => (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                        'cmd' => [
                            'sys_category' => [
                                $uid => [
                                    'localize' => $languageUid,
                                ],
                            ],
                        ],
                        'redirect' => $moduleUrl,
                    ]),
                ];
            }
            $byUid[$uid]['translations'] = $translationsByParent[$uid] ?? [];
            $byUid[$uid]['localizeLinks'] = $localizeLinks;

            $parents = array_filter(GeneralUtility::intExplode(',', (string)$row['parent'], true));
            $parentUid = $parents[0] ?? 0;
            if ($parentUid > 0 && isset($byUid[$parentUid])) {
                $byUid[$parentUid]['children'][] = &$byUid[$uid];
            } else {
                $roots[] = &$byUid[$uid];
            }
        }
        unset($row);

        $this->assignMoveLinks($roots, $moduleUrl);

        return $roots;
    }

    /**
     * Recursively assigns moveUpLink/moveDownLink to each node, based on its position
     * within its sibling list (the list of root nodes, or a node's "children" array).
     * Mirrors the "move up/down" logic of the core Web>List module (DatabaseRecordList),
     * using the "move" DataHandler command with a negative uid to mean "insert after".
     */
    protected function assignMoveLinks(array &$siblings, string $moduleUrl): void
    {
        $count = count($siblings);
        for ($i = 0; $i < $count; $i++) {
            $prevUid = $i > 0 ? (int)$siblings[$i - 1]['uid'] : null;
            $prevPrevUid = $i > 1 ? (int)$siblings[$i - 2]['uid'] : null;
            $nextUid = $i < $count - 1 ? (int)$siblings[$i + 1]['uid'] : null;

            if ($prevUid !== null) {
                $destination = $prevPrevUid !== null ? -$prevPrevUid : (int)$siblings[$i]['pid'];
                $siblings[$i]['moveUpLink'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                    'cmd' => [
                        'sys_category' => [
                            $siblings[$i]['uid'] => [
                                'move' => $destination,
                            ],
                        ],
                    ],
                    'redirect' => $moduleUrl,
                ]);
            }

            if ($nextUid !== null) {
                $siblings[$i]['moveDownLink'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                    'cmd' => [
                        'sys_category' => [
                            $siblings[$i]['uid'] => [
                                'move' => -$nextUid,
                            ],
                        ],
                    ],
                    'redirect' => $moduleUrl,
                ]);
            }

            if (!empty($siblings[$i]['children'])) {
                $this->assignMoveLinks($siblings[$i]['children'], $moduleUrl);
            }
        }
    }

    /**
     * Builds a map of sys_language_uid => ['title' => ..., 'flagIdentifier' => ...]
     * by collecting all languages defined across all sites, so category translations
     * can show a flag/language name instead of a raw "L1" badge.
     */
    protected function getLanguagesByUid(): array
    {
        $languages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $siteLanguage) {
                $languages[$siteLanguage->getLanguageId()] = [
                    'title' => $siteLanguage->getTitle(),
                    'flagIdentifier' => $siteLanguage->getFlagIdentifier(),
                ];
            }
        }

        return $languages;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
