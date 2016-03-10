# Elasticsearch

[![Build Status](https://secure.travis-ci.org/owncloud/search_elastic.png)](http://travis-ci.org/owncloud/search_elastic)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/search_elastic/badges/quality-score.png)](https://scrutinizer-ci.com/g/owncloud/search_elastic/)
[![Scrutinizer Code Coverage](https://scrutinizer-ci.com/g/owncloud/search_elastic/badges/coverage.png)](https://scrutinizer-ci.com/g/owncloud/search_elastic/)

The search_elastic app adds a full text search for files stored in ownCloud. It requires an
[elasticsearch](http://www.elasticsearch.org) server and can index all fitles supported by
 apache tika, eg. plain text, .docx, .xlsx, .pptx, .odt, .ods and .pdf files. The source
code is [available on GitHub](https://github.com/owncloud/search_elastic)

# Design decisions

## Asynchronous indexing & eventually searchable

There are two ways to trigger the indexing of a file. Either you do it immediately after a file is written to (in sync)
or you make a note that the file changed and do it later (asynchronous). The former makes sure that the files and the
index remain consistent. The latter will eventually reach consistency and has significant benefits:

* The original file write operation can complete earlier, which improves syncronization speed.
* Several write operations to the same files can be treated as one, reducing indexing overhead.
* Indexing can happen in a background job

When you recently edited a file, chances are that you still know where it resides and won't use the search to find it.

# Maintainers

* [Jörn Friedrich Dreyer](https://github.com/butonic)

# Known limitations

* Incompatible with encryption.

# Todo

- [x] update elastica lib
- [x] restore compatability with oc8.2
- [x] restore compatability with elasticsearch (requires new indexes)
- [x] store groups and users with access to filter search results by group membership
- [x] store fileid instead of filenames so we don't have to handle renames 
- [x] use instanceid to set up index - allows using the same elasticsearch instance for muliple oc instances
- [x] dont bother storing the filename / path
- sharing a file immediately after it has been uploadad throws an exception
  - [x] fix exception / do not try to update a nonexisting document
  - [x] get all users and groups when initially indexing the document
- [x] move share updates to background job -> eventually searchable
  - [x] descend subdirs when updating
  - [ ] check permissions again on search and remove results if no longer accessible
- [-] --index in batches (make batch size configurable, 0 = unlimited)--
      CLI cron.php executes all jobs
      - [ ] limit number of files to 250? per job?
- [ ] check js for result link handling so clicking a result dos not do a full page load, there seems to be js in place that already does the file highlighting
- [ ] send code snippets for search_lucene
- [ ] use file tab
  - [ ] show index status
  - [ ] remember index error message in db
- [ ] statistics on admin settings page
- [ ] statistics on personal settings page
- [ ] cleanup code
- [ ] port test suite from search_lucene
- [x] resolve path for shared files
