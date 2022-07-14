# Elasticsearch

[![Build Status](https://drone.owncloud.com/api/badges/owncloud/search_elastic/status.svg?branch=master)](https://drone.owncloud.com/owncloud/search_elastic)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=alert_status)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=security_rating)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=coverage)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)

The search_elastic app adds a full text search for files stored in ownCloud. It requires an [elasticsearch](http://www.elasticsearch.org) server and can index all files supported by apache tika, e.g., plain text, .docx, .xlsx, .pptx, .odt, .ods and .pdf files. The source code is available on [GitHub](https://github.com/owncloud/search_elastic). For more information please read the documentation at [https://doc.owncloud.com](https://doc.owncloud.com/server/latest/admin_manual/configuration/general_topics/search.html).

## Maintainers

- [Jörn Friedrich Dreyer](https://github.com/butonic)

## Todo

- [x] update elastica lib
- [x] restore compatibility with oc8.2
- [x] restore compatibility with elasticsearch (requires new indexes)
- [x] store groups and users with access to filter search results by group membership
- [x] store fileid instead of filenames so we don't have to handle renames
- [x] use instanceid to set up index - allows using the same elasticsearch instance for multiple oc instances
- [x] store the filename to allow faster search in shared files
  - index files and folders
- [ ] store tags?
- [ ] store image / video dimensions?
- sharing a file immediately after it has been uploaded throws an exception
  - [x] fix exception / do not try to update a nonexistent document
  - [x] get all users and groups when initially indexing the document
- [x] move share updates to background job -> eventually searchable
  - [x] descend subdirs when updating
  - [x] check permissions again on search and remove results if no longer accessible
    - [ ] compensate for removed entries in search results, too many will confuse the paging logic
- [-] --index in batches (make batch size configurable, 0 = unlimited)--
  CLI cron.php executes all jobs - [ ] limit number of files to 250? per job?
- add occ commands
  - [x] index all files or only those of a specific user
  - [x] enable / disable automatic background scanning via cron
    - [ ] admin settings ui for this
- [x] check js for result link handling so clicking a result dos not do a full page load, there seems to be js in place that already does the file highlighting
  - the old filehandler logic does not seem to work, removed it, now using plain link
- [ ] send code snippets for search_lucene
- [ ] use file tab
  - [ ] show index status
  - [ ] remember index error message in db
- [x] check encryption compatibility
  - [x] had to jump a few hoops to get master key working
  - not compatible with user individual keys
    - [ ] at least index metadata in this case (catch encryption exception and ignore content extraction)
- [ ] statistics on admin settings page
- [ ] statistics on personal settings page
- [x] cleanup code
- [x] port test suite from search_lucene
- [x] resolve path for shared files
- [x] files with empty content extraction are reindexed indefinitely? e.g., empty text file
- [x] more debug logging
- [x] wildcard search ... but there is a bug in core js code preventing wildcard search, see https://github.com/owncloud/core/pull/23531
  - well partly. \* and ? are no longer supported. Instead, we now mimic core, which is called a "match phrase prefix" type query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html#query-dsl-match-query-phrase-prefix
- [ ] to find out why a node cannot be found by its contents mark it as "NO CONTENT EXTRACTED"?
- [ ] how should we handle files in userhome/files_versions/ or userhome/thumbnails/ ... currently a 'vanished' message will be logged ... annoying
