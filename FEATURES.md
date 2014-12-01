# Full text search
As an ownCloud user,
I want to search within all of the documents that I can see in ownCloud
so that I can find the files I need quickly.

Acceptance Criteria:
- [x] Search field is a basic text entry field that takes strings, dates and numeric inputs and searches all documents against that
- [ ] Search field replaces the built in ownCloud file name search and uses the same input field
- [x] Search can be based on basic text strings, and also quotes for exact matches, wild cards “?” and “*”, and the “+” to have a phrase close together, and “-” operator to exclude a phrase
- [ ] Search understands common non-differentiating words in a search phrase and ignores them (e.g. the)
- [x] Search works for MS Office files, PDFs (that are text), libreoffice files, text files – ideally everything elastic search (Lucene) can index, but these are the main ones
- [ ] Search also searches file dates, titles, authors, keywords, and other meta data about the file (if present)
- [x] Search must scale to index GB of files per user, leading up to millions of files per user
- [x] Search must take into account 10,000 users +
- [x] Search must index shared files (files shared with the user) as well as server-server shared files and external storage (external storage may also define a search provider, see below)
- [ ] Search indexing can be toggled on and off for different mount points by the admin
- [x] Results are sorted based on relevance to requested terms

# Full page search results

As a user,
I want search results to be shown in a full navigable page
where search terms can be edited and the search rerun,
and where I can click and get to my file quickly

Acceptance Criteria:
- [ ] Results are paginated, with the first 30 shown inside the search results page
- [ ] Results are displayed like:
  Still working with Jan on the Layout here, but initial concept looks like this:
  ```
(Search field with current search terms in it for the search we are listing)
  
TOTAL RESULTS <a number of identified files in this search>

FILENAME1 <logo of mount point if there is one>
Path to file1
Short excerpt from the document of the search term

FILENAME2 <logo of mount point if there is one>
Path to file
Short excerpt from the document of the search term

FILENAME3 <logo of mount point if there is one>
Path to file
Short excerpt from the document of the search term
```  
  Each filename is then clickable to take you to that file in the ownCloud files app.

# Limit search to subdirectory

As an ownCloud user,
I want to search within all of the documents for a given folder or secondary storage location,
so that I can find the files I need quickly.

Acceptance Criteria:
- [ ] User can navigate into a directory and then the search searches that directory with a checkbox – search only this directory
- [ ] Until a user starts typing in the search field, the “search only this directory” option is hidden – when a user starts typing on the search field, the option to “search only this directory” appears
- [ ] If the user is in the root directory the search only this directory check does not appear
- [ ] Results are returned as in feature full-page-search-results

# Storage based search provider

As an ownCloud developer,
I want to be able to create a search provider for an existing external storage backend (if it exists)
so that I can leverage the native search of different storage locations (e.g. sharepoint, Jive, FileNet)
and not have to index everything

Acceptance Criteria:
- [ ] A mechanism for implementing a search provider for a specific backend is available
- [ ] A search provider can implement some or all of the search capabilities of ownCloud search (such as wildcards and the “+” and “-” operators).
- [ ] When a search of the entire system is executed, the search provider(s) are also queried, and their results are combined into one common result for the user
- [ ] More than one search provider can be implemented based on different backends
- [ ] One search provider handles the case of one or more mounts of different external storage of the same type (e.g. mount 3 different sharepoint servers, the search provider searches all three and returns results)
- [ ] Search provider must be based on a user, so a set of credentials (username, password, token) can be passed to the external storage system to identify the user initiating the search, and only return relevant results for that user

# Search API

As an ownCloud mobile or desktop user,
I want to be able to call the ownCloud search from the device
and find files within ownCloud
so that I can quickly have access wherever I am,
from any device

Acceptance Criteria:
- [ ] Develops of other apps can call a server API that includes all of the search terms that are in the ownCloud server side application
- [ ] Results of the search are returned in a list similar to the web based search results, and displayed locally
- [ ] Results include paths to the results so that the calling application can make the search results clickable for the user on the mobile device

# Scalable full text search

As an ownCloud customer,
I want to be able to implement a set of virtual appliances
that create a search cluster that can scale as I need,
so that I can ensure a good search experience for my users

Acceptance Criteria:
- [ ] Need to package this so it is simple to deploy for the admin of a 500-1000 user setup
- [ ] Need to enable it to have added capacity as needed to scale to also back up a 100,000 user setup
- [ ] Need to have documentation and integration recommendations for how this would be set up
