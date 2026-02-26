# Changelog

All notable changes to this project will be documented in this file.

## [2.4.4](https://github.com/le-phare/import/compare/v2.4.3...v2.4.4) (2026-02-26)


### Bug Fixes

* **import:** fix DirectoryNotFoundException on quarantine directory rotation ([#43](https://github.com/le-phare/import/issues/43)) ([3a9c12e](https://github.com/le-phare/import/commit/3a9c12e2c77f837939d8a270c5025944a2bbcf14))

## [2.4.3](https://github.com/le-phare/import/compare/v2.4.2...v2.4.3) (2025-12-08)


### Miscellaneous Chores

* **composer:** allow Symfony 8 ([#39](https://github.com/le-phare/import/issues/39)) ([4504daf](https://github.com/le-phare/import/commit/4504daf5c28f1d5590885b452b8973d68d294a6f))

## [2.4.2](https://github.com/le-phare/import/compare/v2.4.1...v2.4.2) (2025-11-06)


### Bug Fixes

* **transliterator:** replace apostrophes before slugging ([#36](https://github.com/le-phare/import/issues/36)) ([1609562](https://github.com/le-phare/import/commit/1609562533161841531b0885d9a2bfc2cd2ba185))

## [2.4.1](https://github.com/le-phare/import/compare/v2.4.0...v2.4.1) (2025-10-02)


### Bug Fixes

* **composer:** broader symfony/translation-contracts version constraint ([3d68b3b](https://github.com/le-phare/import/commit/3d68b3b5a4f8caace3c459103674064569e6b6a1))

## [2.4.0](https://github.com/le-phare/import/compare/v2.3.0...v2.4.0) (2025-09-30)


### Features

* add unit_work option to quarantine only invalid file ([#27](https://github.com/le-phare/import/issues/27)) ([86b9f80](https://github.com/le-phare/import/commit/86b9f80e1ae668309f39203eb9fd211b79dc771c))
* **import:** add fail_if_not_loaded option ([#30](https://github.com/le-phare/import/issues/30)) ([41976ed](https://github.com/le-phare/import/commit/41976ed51a14b83c44130348cc71a4981a83699a))
* show resource path on debug mode and when exception thrown ([#31](https://github.com/le-phare/import/issues/31)) ([eb3baf5](https://github.com/le-phare/import/commit/eb3baf58a7f3daa6b9b82abb1759f56e13a00e08))

## [2.3.0](https://github.com/le-phare/import/compare/v2.2.2...v2.3.0) (2025-08-25)


### Features

* **doctrine:** add Doctrine DBAL 4 support ([#28](https://github.com/le-phare/import/issues/28)) ([876972a](https://github.com/le-phare/import/commit/876972a7d9c776e9f105eb7530593199a82847bc))

## [2.2.2](https://github.com/le-phare/import/compare/v2.2.1...v2.2.2) (2025-06-30)


### Bug Fixes

* **transliterator:** use symfony/string to replace behat/transliterator ([#22](https://github.com/le-phare/import/issues/22)) ([009a067](https://github.com/le-phare/import/commit/009a0676b03037da7b79b98733ff026760b96c0b))

## [2.2.1](https://github.com/le-phare/import/compare/v2.2.0...v2.2.1) (2025-06-19)


### Bug Fixes

* **composer:** bump transliterator and forceutf8 to fix curly braces issue ([#23](https://github.com/le-phare/import/issues/23)) ([656e361](https://github.com/le-phare/import/commit/656e3610a9fe6464050570920e6f941006bfef04))

## [2.2.0](https://github.com/le-phare/import/compare/v2.1.0...v2.2.0) (2024-05-23)


### Features

* **pgsql:** handle complex ON CONFLICT target expressions ([#7](https://github.com/le-phare/import/issues/7)) ([4cebf33](https://github.com/le-phare/import/commit/4cebf339fdc7962bd96a74ec8c78f00786d36b20))


### Miscellaneous Chores

* **actions:** add release please workflow and lint ([#8](https://github.com/le-phare/import/issues/8)) ([e49ba42](https://github.com/le-phare/import/commit/e49ba421d960ac16b11bcc38deb5e40efff961d3))

## 2.1.0 (2023-12-19)

### Features

* **feat(composer):** allow Symfony 7 ([!3](https://github.com/le-phare/import/pull/3)) ([eba6d3](https://github.com/le-phare/import/commit/eba6d3e11ffaefe82698306dfafc748c9000db2))

## 2.0.1 (2023-11-15)

### Bug Fixes

* **import:**  fix: CSV Loader crash when there is no validation of headers ([!2](https://github.com/le-phare/import/pull/2)) ([e4d5da](https://github.com/le-phare/import/commit/e4d5da2873186312722c15b7e17e6bd3dc878b8f))
## 2.0.0 (2023-11-08)

### Features

* **import:** open source and update from internal framework ([!1](https://github.com/le-phare/import/pull/1)) ([ff6d84](https://github.com/le-phare/import/commit/ff6d84ffdf3b200cc6fec02017402e357cbd7558))

## 1.0 (2018-06-25)

### Features

* **import:** first version
