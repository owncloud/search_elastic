# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.5.0]
This release requires Elastic Search 5.6.x and the `ingest-attachment` processor.

### Changed
- Compatibility with Elastic Search 5.6.x 
- Moved configuration option into dedicated configuration service

### Fixed
- Indexing encrypted files [#23](https://github.com/owncloud/search_elastic/pull/23)

### Removed
- Dropped support for Elastic Search 2.x


[Unreleased]: https://github.com/owncloud/search_elastic/compare/v0.5.0..HEAD
[0.5.0]: https://github.com/owncloud/search_elastic/compare/d1e94c0c7727b0eb73f62331eb52322ff8103824...v0.5.0