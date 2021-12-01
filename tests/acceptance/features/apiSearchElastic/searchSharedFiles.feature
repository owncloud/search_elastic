@api
Feature: Search for content
  As a user
  I would like to be able to search for the content of files
  So that I can find needed files quickly

  Background:
    Given user "Alice" has been created with default attributes and small skeleton files
    And user "Alice" has created folder "/just-a-folder"
    And user "Alice" has created folder "/फन्नि näme"
    And user "Alice" has uploaded file with content "files content" to "/upload.txt"
    And user "Alice" has uploaded file with content "does-not-matter" to "/a-image.png"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "Alice" has uploaded file with content "does-not-matter" to "/just-a-folder/a-image.png"
    And user "Alice" has uploaded file with content "more content" to "/just-a-folder/uploadÜठिF.txt"
    And user "Alice" has uploaded file with content "and one more content" to "/फन्नि näme/upload.txt"
    And user "Alice" has uploaded file with content "does-not-matter" to "/फन्नि näme/a-image.png"
    And user "Alice" has uploaded file "filesForUpload/simple.odt" to "/simple.odt"
    And user "Alice" has uploaded file "filesForUpload/simple.pdf" to "/simple.pdf"
    And the search index has been created

  Scenario Outline: user searches for files shared to him as a single user
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And user "Alice" has shared file "upload.txt" with user "Brian"
    And user "Alice" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    When user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /upload.txt                   |
      | /just-a-folder/upload.txt     |
      | /just-a-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a single user (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And user "Alice" has created folder "/not-indexed-folder"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "Alice" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "Alice" has shared file "not-indexed-upload.txt" with user "Brian"
    And user "Alice" has shared folder "not-indexed-folder" with user "Brian"
    And the search index has been updated
    When user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /not-indexed-upload.txt            |
      | /not-indexed-folder/upload.txt     |
      | /not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a member of a group
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Alice" has shared file "upload.txt" with group "grp1"
    And user "Alice" has shared folder "just-a-folder" with group "grp1"
    And the search index has been updated
    When user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /upload.txt                   |
      | /just-a-folder/upload.txt     |
      | /just-a-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user searches for files shared to him as a member of a group (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Alice" has created folder "/not-indexed-folder"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "Alice" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "Alice" has shared file "not-indexed-upload.txt" with group "grp1"
    And user "Alice" has shared folder "not-indexed-folder" with group "grp1"
    And the search index has been updated
    When user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /not-indexed-upload.txt            |
      | /not-indexed-folder/upload.txt     |
      | /not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And user "Alice" has shared file "upload.txt" with user "Brian"
    And user "Alice" has shared folder "just-a-folder" with user "Brian"
    And user "Alice" has shared file "/फन्नि näme/upload.txt" with user "Brian"
    And user "Brian" has uploaded file with content "files content" to "/Brian-upload.txt"
    And the search index has been updated
    When user "Brian" unshares folder "/just-a-folder" using the WebDAV API
    And user "Brian" unshares file "/upload.txt" using the WebDAV API
    And the search index has been updated
    And user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should not contain these files:
      | /upload.txt                   |
      | /just-a-folder/upload.txt     |
      | /just-a-folder/uploadÜठिF.txt |
    But the search result of user "Brian" should contain these files:
      | /Brian-upload.txt |
      | /upload (2).txt   |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched (files have been indexed only after sharing)
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And user "Alice" has created folder "/not-indexed-folder"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload-keep.txt"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "Alice" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "Alice" has shared file "not-indexed-upload.txt" with user "Brian"
    And user "Alice" has shared file "not-indexed-upload-keep.txt" with user "Brian"
    And user "Alice" has shared folder "not-indexed-folder" with user "Brian"
    And user "Brian" has uploaded file with content "files content" to "/Brian-upload.txt"
    And the search index has been updated
    When user "Brian" unshares folder "/not-indexed-folder" using the WebDAV API
    And user "Brian" unshares file "/not-indexed-upload.txt" using the WebDAV API
    And the search index has been updated
    And user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should not contain these files:
      | /not-indexed-upload.txt            |
      | /not-indexed-folder/upload.txt     |
      | /not-indexed-folder/uploadÜठिF.txt |
    But the search result of user "Brian" should contain these files:
      | /Brian-upload.txt            |
      | /not-indexed-upload-keep.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched (files have been indexed only after unsharing)
    Given using <dav_version> DAV path
    And user "Brian" has been created with default attributes and small skeleton files
    And user "Alice" has created folder "/not-indexed-folder"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload-keep.txt"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "Alice" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "Alice" has shared file "not-indexed-upload.txt" with user "Brian"
    And user "Alice" has shared file "not-indexed-upload-keep.txt" with user "Brian"
    And user "Alice" has shared folder "not-indexed-folder" with user "Brian"
    And user "Brian" has uploaded file with content "files content" to "/Brian-upload.txt"
    When user "Brian" unshares folder "/not-indexed-folder" using the WebDAV API
    And user "Brian" unshares file "/not-indexed-upload.txt" using the WebDAV API
    And the search index has been updated
    And user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should not contain these files:
      | /not-indexed-upload.txt            |
      | /not-indexed-folder/upload.txt     |
      | /not-indexed-folder/uploadÜठिF.txt |
    But the search result of user "Brian" should contain these files:
      | /Brian-upload.txt            |
      | /not-indexed-upload-keep.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: users searches for files re-shared to him
    Given using <dav_version> DAV path
    And these users have been created with default attributes and small skeleton files:
      | username |
      | Brian    |
      | Carol    |
    And user "Alice" has shared file "upload.txt" with user "Brian"
    And user "Alice" has shared folder "just-a-folder" with user "Brian"
    And user "Brian" has shared file "upload.txt" with user "Carol"
    And user "Brian" has shared folder "just-a-folder" with user "Carol"
    And the search index has been updated
    When user "Carol" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Carol" should contain these files:
      | /upload.txt                   |
      | /just-a-folder/upload.txt     |
      | /just-a-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: users searches for files re-shared to him (files have been indexed only after second sharing)
    Given using <dav_version> DAV path
    And these users have been created with default attributes and small skeleton files:
      | username |
      | Brian    |
      | Carol    |
    And user "Alice" has created folder "/not-indexed-folder"
    And user "Alice" has uploaded file with content "files content" to "/not-indexed-upload.txt"
    And user "Alice" has uploaded file with content "file with content in subfolder" to "/not-indexed-folder/upload.txt"
    And user "Alice" has uploaded file with content "more content" to "/not-indexed-folder/uploadÜठिF.txt"
    And user "Alice" has shared file "not-indexed-upload.txt" with user "Brian"
    And user "Alice" has shared folder "not-indexed-folder" with user "Brian"
    And user "Brian" has shared file "not-indexed-upload.txt" with user "Carol"
    And user "Brian" has shared folder "not-indexed-folder" with user "Carol"
    And the search index has been updated
    When user "Carol" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Carol" should contain these files:
      | /not-indexed-upload.txt            |
      | /not-indexed-folder/upload.txt     |
      | /not-indexed-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: new files in a shared folder get indexed for all users
    Given using <dav_version> DAV path
    And these users have been created with default attributes and small skeleton files:
      | username |
      | Brian    |
      | Carol    |
    And user "Alice" has shared folder "just-a-folder" with user "Brian"
    And user "Brian" has shared folder "just-a-folder" with user "Carol"
    When user "Alice" uploads file with content "new file content" to "/just-a-folder/new-upload-Alice.txt" using the WebDAV API
    And user "Brian" uploads file with content "new file content" to "/just-a-folder/new-upload-Brian.txt" using the WebDAV API
    And user "Carol" uploads file with content "new file content" to "/just-a-folder/new-upload-Carol.txt" using the WebDAV API
    And the search index has been updated
    And user "Alice" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /just-a-folder/new-upload-Alice.txt |
      | /just-a-folder/new-upload-Brian.txt |
      | /just-a-folder/new-upload-Carol.txt |
    When user "Brian" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /just-a-folder/new-upload-Alice.txt |
      | /just-a-folder/new-upload-Brian.txt |
      | /just-a-folder/new-upload-Carol.txt |
    When user "Carol" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Carol" should contain these files:
      | /just-a-folder/new-upload-Alice.txt |
      | /just-a-folder/new-upload-Brian.txt |
      | /just-a-folder/new-upload-Carol.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: changed files in a shared folder get indexed for all users
    Given using <dav_version> DAV path
    And these users have been created with default attributes and small skeleton files:
      | username |
      | Brian    |
      | Carol    |
    And user "Alice" has shared folder "just-a-folder" with user "Brian"
    And user "Brian" has shared folder "just-a-folder" with user "Carol"
    When user "Carol" uploads file with content "files with a change of content" to "/just-a-folder/upload.txt" using the WebDAV API
    And the search index has been updated
    And user "Alice" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /just-a-folder/upload.txt |
    When user "Brian" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /just-a-folder/upload.txt |
    When user "Carol" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Carol" should contain these files:
      | /just-a-folder/upload.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
