# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.5.4]

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


[Unreleased]: https://github.com/owncloud/search_elastic/compare/v0.5.4..HEAD
[0.5.4]: https://github.com/owncloud/search_elastic/compare/v0.5.2...v0.5.4
[0.5.2]: https://github.com/owncloud/search_elastic/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/owncloud/search_elastic/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/owncloud/search_elastic/compare/d1e94c0c7727b0eb73f62331eb52322ff8103824...v0.5.0