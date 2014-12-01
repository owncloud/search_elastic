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

- [ ] port test suite from search_lucene
- [ ] resolve path for shared files
