# Categories Tree Module for TYPO3

This extension provides a dedicated backend module ("Web > Categories") that
renders `sys_category` records as a real tree (like the page tree), instead of
the flat Web > List view. It supports a language filter and inline record
actions (edit, hide/enable, delete, create subcategory).

Features
--------
- Backend module `web_CategoryTree`, listed under "Web", showing all
  `sys_category` records as a hierarchical tree.
- Toggle to show/hide translated (non-default-language) category records.
- Inline actions per category: edit, enable/disable, delete, add subcategory,
  move up/down, localize to another language.
- Prefixes translated category titles with `[Translate to <language>:]` when
  created via "Localize to", matching the behaviour of `pages` and other core
  tables.

Requirements
------------
- Composer for installation
- TYPO3 v13.4+

Installation
------------
Install via Composer in your TYPO3 project root:

```bash
composer require itx/edit-categories
```

After installation, clear the TYPO3 caches and check the extension list in
the backend.

Usage
-----
1. Open the "Web > Categories" module in the TYPO3 backend.
2. Browse `sys_category` records as a tree.
3. Use the toggle to show/hide translated categories.
4. Use the inline actions to edit, hide/enable, delete, move or localize a
   category, or to create a new (sub-)category.

Configuration
-------------
- No further configuration is required; the module and its actions are
  available out of the box after installation.

Development / Contributing
--------------------------
Contributions are welcome. Please open an issue or a pull request on the
repository. When contributing:

- Follow PSR-12 coding style where possible.
- Add tests for new functionality if applicable.
- Update `CHANGELOG` when creating releases.

Support
-------
If you encounter problems or bugs, please open an issue on the repository.

License
-------
This extension is licensed under the GPL-2.0-or-later.

Changelog
---------
See the `Changelog/` directory for release notes and history.

Notes
-----
- The extension installs as a Composer package and will be placed in
  `vendor/` by Composer. If you require installation into `typo3conf/ext/`,
  configure installer paths accordingly.
