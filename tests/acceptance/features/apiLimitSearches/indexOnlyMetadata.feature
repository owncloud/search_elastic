@api
Feature: index only metadata
As a administrator
I would like to be able to disable content indexing
So that I can use search_elastic only as a more scalable search on filenames

  Background:
    Given user "user0" has been created with default attributes
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
    And the administrator has configured the search_elastic app to index only metadata
    And files of user "user0" have been indexed

  Scenario Outline: search for content
    Given using <dav_version> DAV path
    When user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
      |/simple.odt                  |
      |/simple.pdf                  |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for filename
    Given using <dav_version> DAV path
    When user "user0" searches for "a-image.png" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/a-image.png                 |
      |/just-a-folder/a-image.png   |
      |/फन्नि näme/a-image.png         |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for filename (not exact case)
    Given using <dav_version> DAV path
    When user "user0" searches for "A-iMagE.png" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/a-image.png                 |
      |/just-a-folder/a-image.png   |
      |/फन्नि näme/a-image.png         |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for filename (not full word - end of filename missing)
    Given using <dav_version> DAV path
    When user "user0" searches for "uplo" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-40
  Scenario Outline: search for filename (not full word - start of filename missing)
    Given using <dav_version> DAV path
    When user "user0" searches for "ad.txt" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/फन्नि näme/upload.txt          |
    But the search result should not contain these files:
      |/just-a-folder/uploadÜठिF.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-40
  Scenario Outline: search for filename (just file extension)
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "does-not-matter" to "/a-png-file.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for ".png" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/a-image.png                 |
      |/just-a-folder/a-image.png   |
      |/फन्नि näme/a-image.png         |
    #But the search result should not contain these files:
    But the search result should contain these files:
      |/a-png-file.txt              |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-40
  Scenario Outline: search for filename (not full word - only middle part of filename given)
    Given using <dav_version> DAV path
    When user "user0" searches for "oad" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for filename (edge case)
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "does not matter" to "<filename>"
    And files of user "user0" have been indexed
    When user "user0" searches for <search> using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |<filename> |
    Examples:
      | dav_version | filename    | search |
      | old         | /000        | "000"  |
      | new         | /000        | "000"  |
      | old         | /000        | "  0"  |
      | new         | /000        | "  0"  |
      | old         | /text -1 t  | " -1"  |
      | new         | /text -1 t  | " -1"  |
      | old         | /false      | "false"|
      | new         | /false      | "false"|
      | old         | /null       | "null" |
      | new         | /null       | "null" |

  Scenario Outline: search for filename - pattern matches filename and content
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "this file is uploaded to oC" to "/content-is-secret.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/content-is-secret.txt       |
    But the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
      |/फन्नि näme/upload.txt          |
      |/simple.odt                  |
      |/simple.pdf                  |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for filename by UTF pattern
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "मेरो नेपालि content" to "/uploadÜठिF.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for "uploadÜठिF" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/uploadÜठिF.txt|
      |/uploadÜठिF.txt              |
    But the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/फन्नि näme/upload.txt          |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for deleted files should not work
    Given using <dav_version> DAV path
    When user "user0" deletes file "/upload.txt" using the WebDAV API
    And user "user0" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
      |/फन्नि näme/upload.txt          |
    But the search result should not contain these files:
      |/upload.txt                  |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-39
  Scenario Outline: search for renamed file
    Given using <dav_version> DAV path
    When user "user0" moves file "/upload.txt" to "/renamed_textfile0.txt" using the WebDAV API
    And the administrator indexes files of user "user0"
    And user "user0" searches for "renamed" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/renamed_textfile0.txt       |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-39
  Scenario Outline: search for changed filenames, search for part of the name that was in the original file, but not in the new file
    Given using <dav_version> DAV path
    When user "user0" moves file "/upload.txt" to "/renamed_textfile0.txt" using the WebDAV API
    And the administrator indexes files of user "user0"
    And user "user0" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
      |/फन्नि näme/upload.txt          |
    #And the search result should not contain these files:
    And the search result should contain these files:
      |/renamed_textfile0.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user should not be able to search in files of other users
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes
    And user "user1" has uploaded file with content "files content" to "/upload-user1.txt"
    And all files have been indexed
    When user "user1" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/upload-user1.txt                  |
    But the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-36
   Scenario Outline: user searches for files shared to him as a single user
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes
    And user "user0" has shared file "upload.txt" with user "user1"
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    When user "user1" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  @issue-36
  Scenario Outline:  user searches for files shared to him as a member of a group
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user0" has shared file "upload.txt" with group "grp1"
    And user "user0" has shared folder "just-a-folder" with group "grp1"
    And all files have been indexed
    When user "user1" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: Unshared files should not be searched
    Given using <dav_version> DAV path
    And user "user1" has been created with default attributes
    And user "user0" has shared file "upload.txt" with user "user1"
    And user "user0" has shared folder "just-a-folder" with user "user1"
    And user "user1" has uploaded file with content "files content" to "/upload-user1.txt"
    And all files have been indexed
    When user "user1" unshares folder "/just-a-folder" using the WebDAV API
    And user "user1" unshares file "/upload.txt" using the WebDAV API
    And the administrator indexes all files
    And user "user1" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should not contain these files:
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt |
    But the search result should contain these files:
      |/upload-user1.txt            |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @local_storage
  Scenario Outline: search on local storage
    Given using <dav_version> DAV path
    And user "user0" has moved file "/upload.txt" to "/local_storage/upload.txt"
    And user "user0" has created folder "/local_storage/just-a-folder"
    And user "user0" has moved file "/just-a-folder/upload.txt" to "/local_storage/just-a-folder/upload.txt"
    And the administrator indexes files of user "user0"
    And user "user0" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/local_storage/upload.txt       |
      |/local_storage/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |