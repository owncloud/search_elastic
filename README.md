# Elasticsearch

[![Build Status](https://secure.travis-ci.org/owncloud/search_elastic.png)](http://travis-ci.org/owncloud/search_elastic)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/search_elastic/badges/quality-score.png)](https://scrutinizer-ci.com/g/owncloud/search_elastic/)
[![Scrutinizer Code Coverage](https://scrutinizer-ci.com/g/owncloud/search_elastic/badges/coverage.png)](https://scrutinizer-ci.com/g/owncloud/search_elastic/)

The search_elastic app adds a full text search for files stored in ownCloud. It requires an
[elasticsearch](http://www.elasticsearch.org) server and can index all fitles supported by
 apache tika, eg. plain text, .docx, .xlsx, .pptx, .odt, .ods and .pdf files. The source
code is [available on GitHub](https://github.com/owncloud/search_elastic)

## Installation ef elasticsearch
- Do not use 2.2 / 2.3, The bugfix for indexing docx files needs to be released: https://github.com/elastic/elasticsearch/pull/17059 
- Downloud elasticsearch 2.1.2 from https://www.elastic.co/downloads/past-releases/elasticsearch-2-1-2
- after installation install attachments mapper plugin: `bin/plugin install elasticsearch/elasticsearch-mapper-attachments/3.1.2`

## Installation of search_elastic
- install & enable the app
- go to the admin settings, set up url and port, click "setup index"  

To trigger indexing create, upload or change a file. The next cron.php will index all unindexed files for the user who did the change.

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

# Todo

- [x] update elastica lib
- [x] restore compatability with oc8.2
- [x] restore compatability with elasticsearch (requires new indexes)
- [x] store groups and users with access to filter search results by group membership
- [x] store fileid instead of filenames so we don't have to handle renames 
- [x] use instanceid to set up index - allows using the same elasticsearch instance for muliple oc instances
- [ ] store the filename to allow faster search in shared files
  - index files and folders
- [ ] store tags?
- sharing a file immediately after it has been uploadad throws an exception
  - [x] fix exception / do not try to update a nonexisting document
  - [x] get all users and groups when initially indexing the document
- [x] move share updates to background job -> eventually searchable
  - [x] descend subdirs when updating
  - [x] check permissions again on search and remove results if no longer accessible
    - [ ] compensate for removed entries in search results, too many will confuse the paging logic
- [-] --index in batches (make batch size configurable, 0 = unlimited)--
      CLI cron.php executes all jobs
      - [ ] limit number of files to 250? per job?
- add occ commands
  - [ ] index all files or only those of a specific user
  - [ ] enable / disable automatic background scanning via cron
    - [ ] admin settings ui for this 
- [x] check js for result link handling so clicking a result dos not do a full page load, there seems to be js in place that already does the file highlighting
     - the old filehandler logic does not seem to work, removed it, now using plain link
- [ ] send code snippets for search_lucene
- [ ] use file tab
  - [ ] show index status
  - [ ] remember index error message in db
- [ ] check encryption compatability (master key mode should work)
- [ ] statistics on admin settings page
- [ ] statistics on personal settings page
- [ ] cleanup code
- [ ] port test suite from search_lucene
- [x] resolve path for shared files
- [ ] files with empty content extraction are reindexed indefinitely? eg empty text file
- [x] more debug logging
