<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Prefix the title with "[Translate to <language>:]" when a new translation
// is created via the "Localize to" action, mirroring the behaviour of
// "pages" and other core tables. Without this, sys_category translations
// silently copy the default language title with no visual indication that
// it still needs to be translated.
$GLOBALS['TCA']['sys_category']['columns']['title']['l10n_mode'] = 'prefixLangTitle';
