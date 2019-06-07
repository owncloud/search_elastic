@api
Feature: Search for content
As a user
I would like to be able to search for the content of files
So that I can find needed files quickly

  Background:
    Given user "user0" has been created with default attributes and skeleton files
    And user "user0" has created folder "/just-a-folder"
    And user "user0" has created folder "/फन्नि näme"
    And user "user0" has uploaded file with content "files content" to "/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/a-image.png"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/just-a-folder/a-image.png"
    And user "user0" has uploaded file with content "more content" to "/just-a-folder/uploadÜठिF.txt"
    And user "user0" has uploaded file with content "and one more content" to "/फन्नि näme/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/फन्नि näme/a-image.png"
    And user "user0" has uploaded file "filesForUpload/simple.odt" to "/simple.odt"
    And user "user0" has uploaded file "filesForUpload/simple.pdf" to "/simple.pdf"
    And the search index has been built

  Scenario Outline: user searches for files shared to him as a single user
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has shared file "upload.txt" with user "user1"
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And the search index has been updated
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a single user (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created folder "/not-indexed-folder"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "user0" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "user0" has shared file "not-indexed-upload.txt" with user "user1"
    And user "user0" has shared folder "not-indexed-folder" with user "user1"
    And the search index has been updated
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/not-indexed-upload.txt           |
      |/not-indexed-folder/upload.txt    |
      |/not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a member of a group
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user0" has shared file "upload.txt" with group "grp1"
    And user "user0" has shared folder "just-a-folder" with group "grp1"
    And the search index has been updated
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a member of a group (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user0" has created folder "/not-indexed-folder"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "user0" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "user0" has shared file "not-indexed-upload.txt" with group "grp1"
    And user "user0" has shared folder "not-indexed-folder" with group "grp1"
    And the search index has been updated
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/not-indexed-upload.txt           |
      |/not-indexed-folder/upload.txt    |
      |/not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has shared file "upload.txt" with user "user1"
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And user "user0" has shared file "/फन्नि näme/upload.txt" with user "user1"
    And user "user1" has uploaded file with content "files content" to "/user1-upload.txt"
    And the search index has been updated
    When user "user1" unshares folder "/just-a-folder" using the WebDAV API
    And user "user1" unshares file "/upload.txt" using the WebDAV API
    And the search index has been updated
    And user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
    But the search result should contain these files:
      |/user1-upload.txt            |
      |/upload (2).txt              |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created folder "/not-indexed-folder"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload-keep.txt"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "user0" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "user0" has shared file "not-indexed-upload.txt" with user "user1"
    And user "user0" has shared file "not-indexed-upload-keep.txt" with user "user1"
    And user "user0" has shared folder "not-indexed-folder" with user "user1"
    And user "user1" has uploaded file with content "files content" to "/user1-upload.txt"
    And the search index has been updated
    When user "user1" unshares folder "/not-indexed-folder" using the WebDAV API
    And user "user1" unshares file "/not-indexed-upload.txt" using the WebDAV API
    And the search index has been updated
    And user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should not contain these files:
      |/not-indexed-upload.txt           |
      |/not-indexed-folder/upload.txt    |
      |/not-indexed-folder/uploadÜठिF.txt |
    But the search result should contain these files:
      |/user1-upload.txt                 |
      |/not-indexed-upload-keep.txt      |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched (files have been indexed only after unsharing)
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created folder "/not-indexed-folder"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload-keep.txt"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "user0" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "user0" has shared file "not-indexed-upload.txt" with user "user1"
    And user "user0" has shared file "not-indexed-upload-keep.txt" with user "user1"
    And user "user0" has shared folder "not-indexed-folder" with user "user1"
    And user "user1" has uploaded file with content "files content" to "/user1-upload.txt"
    When user "user1" unshares folder "/not-indexed-folder" using the WebDAV API
    And user "user1" unshares file "/not-indexed-upload.txt" using the WebDAV API
    And the search index has been updated
    And user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should not contain these files:
      |/not-indexed-upload.txt           |
      |/not-indexed-folder/upload.txt    |
      |/not-indexed-folder/uploadÜठिF.txt |
    But the search result should contain these files:
      |/user1-upload.txt                 |
      |/not-indexed-upload-keep.txt      |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: users searches for files re-shared to him
    Given using <dav_version> DAV path
    And these users have been created with default attributes and skeleton files:
      | username |
      | user1    |
      | user2    |
    And user "user0" has shared file "upload.txt" with user "user1"
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And user "user1" has shared file "upload.txt" with user "user2"
    And user "user1" has shared folder "just-a-folder" with user "user2"
    And the search index has been updated
    When user "user2" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: users searches for files re-shared to him (files have been indexed only after second sharing)
    Given using <dav_version> DAV path
    And these users have been created with default attributes and skeleton files:
      | username |
      | user1    |
      | user2    |
    And user "user0" has created folder "/not-indexed-folder"
    And user "user0" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "user0" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "user0" has shared file "not-indexed-upload.txt" with user "user1"
    And user "user0" has shared folder "not-indexed-folder" with user "user1"
    And user "user1" has shared file "not-indexed-upload.txt" with user "user2"
    And user "user1" has shared folder "not-indexed-folder" with user "user2"
    And the search index has been updated
    When user "user2" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/not-indexed-upload.txt           |
      |/not-indexed-folder/upload.txt    |
      |/not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: new files in a shared folder get indexed for all users
    Given using <dav_version> DAV path
    And these users have been created with default attributes and skeleton files:
      | username |
      | user1    |
      | user2    |
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And user "user1" has shared folder "just-a-folder" with user "user2"
    When user "user0" uploads file with content "new file content" to "/just-a-folder/new-upload-user0.txt" using the WebDAV API
    And user "user1" uploads file with content "new file content" to "/just-a-folder/new-upload-user1.txt" using the WebDAV API
    And user "user2" uploads file with content "new file content" to "/just-a-folder/new-upload-user2.txt" using the WebDAV API
    And the search index has been updated
    And user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/new-upload-user0.txt |
      |/just-a-folder/new-upload-user1.txt |
      |/just-a-folder/new-upload-user2.txt |
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/new-upload-user0.txt |
      |/just-a-folder/new-upload-user1.txt |
      |/just-a-folder/new-upload-user2.txt |
    When user "user2" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/new-upload-user0.txt |
      |/just-a-folder/new-upload-user1.txt |
      |/just-a-folder/new-upload-user2.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: changed files in a shared folder get indexed for all users
    Given using <dav_version> DAV path
    And these users have been created with default attributes and skeleton files:
      | username |
      | user1    |
      | user2    |
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And user "user1" has shared folder "just-a-folder" with user "user2"
    When user "user2" uploads file with content "files with changed content" to "/just-a-folder/upload.txt" using the WebDAV API
    And the search index has been updated
    And user "user0" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/upload.txt |
    When user "user1" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/upload.txt |
    When user "user2" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/upload.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
