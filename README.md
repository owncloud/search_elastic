# Elasticsearch

[![Build Status](https://drone.owncloud.com/api/badges/owncloud/search_elastic/status.svg?branch=master)](https://drone.owncloud.com/owncloud/search_elastic)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=alert_status)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=security_rating)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_search_elastic&metric=coverage)](https://sonarcloud.io/dashboard?id=owncloud_search_elastic)

The search_elastic app adds a full text search for files stored in ownCloud. It requires an
[elasticsearch](http://www.elasticsearch.org) server and can index all files supported by
 apache tika, e.g., plain text, .docx, .xlsx, .pptx, .odt, .ods and .pdf files. The source
code is [available on GitHub](https://github.com/owncloud/search_elastic)

## Installation of elasticsearch

### Elasticsearch 7

> Elasticsearch 7 is supported in `search_elastic-2.0.0`

### Elasticsearch 6

> Elasticsearch 6 is not supported

### Elasticsearch 5.6.x

`search_elastic` requires [ingest-attachment](https://www.elastic.co/guide/en/elasticsearch/plugins/5.6/ingest-attachment.html) processor to be present

```console
$ cd /usr/share/elasticsearch/
$ bin/elasticsearch-plugin install ingest-attachment
$ service elasticsearch restart
```

Or, if you used the tarball:
```console
$ cd /path/to/unzipped/tar
$ bin/elasticsearch-plugin install ingest-attachment
$ bin/elasticsearch
```

##### testing locally with docker

To have an elastic-search running locally, use `docker-compose  -f tests/docker-compose.yml up `.
It will build an image with the ingest-attachment plugin available and expose elasticsearch locally at port 9200/9300 


### Elasticsearch 2.x

> Elasticsearch 2.x is only supported in app versions up to 0.2.5.


## Installation of search_elastic
- install & enable the app
- go to the admin settings, set up url and port, click "reset index"  

To trigger indexing create, upload or change a file. The next cron.php will index all unindexed files for the user who did the change.

# App Modes and Configuration

After enabling the app it will be in *active mode*
- file changes will be indexed in background jobs (System
  cron is recommended, because otherwise a lot of jobs might queue up).
- Search results will be based on elasticsearch.
- The core search functionality based on database queries will no longer be used.
This might cause a downtime for search when enabling the app in an
already heavily used instance because it takes a while until all files
have been indexed.

## App mode

To do an initial full indexing, without the app interfering it can
be put in *passive mode* with 
```
# sudo -u www-data ./occ config:app:set search_elastic mode --value passive
```
- The administrator will be able to run occ commands
- The app will not index any changes by itself.
- Search results will still be based on the core search.
Switching back to active mode can be done with
```
# sudo -u www-data ./occ config:app:set search_elastic mode --value active
```

## Limit search_elastic access to a group

It is possible to limit the users that will have access to full text
search by setting a group e.g., to 'admin' with
```
# sudo -u www-data php occ config:app:set search_elastic group --value admin
```
This will cause only members of the admin group to do a full text search.
If you want the index to be built subsequently in *active mode* use a 
group that no user is a member of or that des not exist, e.g., 'nobody'.
If you leave the group empty every user will be able to use the app.
This functionality also allows you to provide full text search as an 
added value e.g., for the 'premium' users.

## Only index metadata

If you only want to use search_elastic as a more scalable search on 
filenames you can disable content indexing by setting `nocontent` to 
`true` (default is `false`):
```
# sudo -u www-data php occ config:app:set search_elastic nocontent --value true
```

Note that you will have to reindex all files if you change this back to
`false`. Setting it to `true` does not require reindexing. Nevertheless,
go with limiting full text search to certain groups, by setting 
`group.nocontent` which is more flexible anyway.

## Limit a group to only search in metadata

If you only want to use the search in shared filenames you can disable
full text search for a specific group by setting `group.nocontent` to the
group whose users should only receive results based on filenames (not the
full path), e.g., users in group 'nofulltext':
```
# sudo -u www-data php occ config:app:set search_elastic group.nocontent --value nofulltext
```
You can also configure multiple groups by separating them with comma:
```
# sudo -u www-data php occ config:app:set search_elastic group.nocontent --value nofulltext,anothergroup,"group with blanks"
```
This allows a scalable search in shared files without clouding the
results with content based hits. 


# Commands

## search:index:create
Create a search index from scratch. Use it for a single user by entering the username as an argument. Use `--all` to run it for all users.
```
# sudo -u www-data php occ search:index:create --all
Indexing user admin
Indexing user user1
Indexing user user2
...
```

This should be used to index all files for the first time. It doesn't update changes to the metadata correctly, if you run it multiple times.

- [ ] add console output for every file (currently using loglevel debug
      will log what is going on)

## search:index:update
Updates to the search index due to changed content or changed metadata are 
happening via background jobs that are added to a queue. These background jobs are normally run by the ownCloud cronjob.
Use this command to run the background jobs more often.
```
# sudo -u www-data php occ search:index:update
```

## search:index:reset
Reset an index. Needs to be run once before first indexing. Use the `--force` option to skip further warning messages.
```
# sudo -u www-data php occ search:index:reset --force
```

## search:index:rebuild
Reset an index for a single user and recreate it from scratch. Use the `--force` option to skip further warning messages.
```
# sudo -u www-data php occ search:index:rebuild USERID --force
```

# Design decisions

## Asynchronous indexing & eventually searchable

There are two ways to trigger the indexing of a file. Either you do it immediately after a file is written to (in sync)
or you make a note that the file changed and do it later (asynchronous). The former makes sure that the files and the
index remain consistent. The latter will eventually reach consistency and has significant benefits:

* The original file write operation can complete earlier, which improves synchronization speed.
* Several write operations to the same files can be treated as one, reducing indexing overhead.
* Indexing can happen in a background job

When you recently edited a file, chances are that you still know where it resides and won't use the search to find it.

# Maintainers

* [Jörn Friedrich Dreyer](https://github.com/butonic)

# Todo

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
      CLI cron.php executes all jobs
      - [ ] limit number of files to 250? per job?
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
  - well partly. * and ? are no longer supported. Instead, we now mimic core, which is called a "match phrase prefix" type query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html#query-dsl-match-query-phrase-prefix
- [ ] to find out why a node cannot be found by its contents mark it as "NO CONTENT EXTRACTED"? 
- [ ] how should we handle files in userhome/files_versions/ or userhome/thumbnails/  ... currently a 'vanished' message will be logged ... annoying 
