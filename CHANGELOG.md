# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [2.1.0] - 2022-03-15

### Changed

- Validate if there are elastic search servers configured before indexing the nodes pending updates. - [#4944](https://github.com/owncloud/enterprise/issues/4944)
- Add user and password authentication. - [#246](https://github.com/owncloud/search_elastic/issues/246)

### Fixed

- [QA] Option 'Scan external storages' cannot be enabled in the web UI once disabled - [#260](https://github.com/owncloud/search_elastic/issues/246)


## [2.0.0] - 2021-12-03

### Changed
- Support ES7 [#215](https://github.com/owncloud/search_elastic/pull/215)
- [Feature] make Search more greedy [#212](https://github.com/owncloud/search_elastic/pull/212)

### Fixed
- Full Text Search 1.0 cannot connect to elasticsearch 1.0 [#197](https://github.com/owncloud/search_elastic/pull/197)


## [1.0.0] - 2019-11-21

### Changed
- Rename build Command to create [#119](https://github.com/owncloud/search_elastic/pull/119)
- Add phan and phpstan matrix in drone [#90](https://github.com/owncloud/search_elastic/pull/90)
- Migrate Namespace to PSR-4 [#91](https://github.com/owncloud/search_elastic/pull/91)
- Ask security questions on destructive commands [#89](https://github.com/owncloud/search_elastic/pull/89)
- Refactor Commands [#85](https://github.com/owncloud/search_elastic/pull/85)
- Updated dependencies [#151](https://github.com/owncloud/search_elastic/pull/151)

### Fixed
- React to federated share accept, trashbin restore and versions restore [#131](https://github.com/owncloud/search_elastic/pull/131)
- Check if user exists before asking questions in command line [#128](https://github.com/owncloud/search_elastic/pull/128)
- Adding group restrictions should not prohibit search for admin users [#129](https://github.com/owncloud/search_elastic/pull/129)
- Remove deprecated shipped tag [#87](https://github.com/owncloud/search_elastic/pull/87)
- Fix search via DAV when app is enabled for groups only [#83](https://github.com/owncloud/search_elastic/pull/83)

## [0.5.4] - 2019-03-18

### Removed
- Drop PHP 5.6 [#77](https://github.com/owncloud/search_elastic/pull/77)

### Fixed

- Fix Mapper Insert method [#75](https://github.com/owncloud/search_elastic/pull/75)

## [0.5.3]
Skipped

## [0.5.2]

### Fixed
- Collabora edits are not updated due to incognito mode [#71](https://github.com/owncloud/search_elastic/pull/71)

## [0.5.1]

### Fixed
- Incorrect indexing for shared folders in `occ search:index` [#28](https://github.com/owncloud/search_elastic/pull/28)

## [0.5.0]
This release requires Elastic Search 5.6.x and the `ingest-attachment` processor.

### Changed
- Compatibility with Elastic Search 5.6.x 
- Moved configuration option into dedicated configuration service

### Fixed
- Indexing encrypted files [#23](https://github.com/owncloud/search_elastic/pull/23)

### Removed
- Dropped support for Elastic Search 2.x

[Unreleased]: https://github.com/owncloud/search_elastic/compare/v2.1.0...master
[2.1.0]: https://github.com/owncloud/search_elastic/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/owncloud/search_elastic/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/owncloud/search_elastic/compare/v0.5.4...v1.0.0
[0.5.4]: https://github.com/owncloud/search_elastic/compare/v0.5.2...v0.5.4
[0.5.2]: https://github.com/owncloud/search_elastic/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/owncloud/search_elastic/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/owncloud/search_elastic/compare/d1e94c0c7727b0eb73f62331eb52322ff8103824...v0.5.0
