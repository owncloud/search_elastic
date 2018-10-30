@api
Feature: Search for content
As a user
I would like to be able to search for the content of files
So that I can find needed files quickly

  Background:
    Given user "user0" has been created
    And user "user0" has created a folder "/just-a-folder"
    And user "user0" has created a folder "/फन्नि näme"
    And user "user0" has uploaded file with content "files content" to "/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/a-image.png"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/just-a-folder/a-image.png"
    And user "user0" has uploaded file with content "more content" to "/just-a-folder/uploadÜठिF.txt"
    And user "user0" has uploaded file with content "and one more content" to "/फन्नि näme/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/फन्नि näme/a-image.png"
    And user "user0" has uploaded file "filesForUpload/simple.odt" to "/simple.odt"
    And user "user0" has uploaded file "filesForUpload/simple.pdf" to "/simple.pdf"
    And files of user "user0" have been indexed

  Scenario Outline: search for files by pattern
    Given using <dav_version> DAV path
    When user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
      |/simple.odt                  |
      |/simple.pdf                  |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for files by pattern (not exact case)
    Given using <dav_version> DAV path
    When user "user0" searches for "oWncLOUd" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/textfile0.txt         |
      |/textfile1.txt         |
      |/textfile2.txt         |
      |/textfile3.txt         |
      |/textfile4.txt         |
      |/PARENT/CHILD/child.txt|
      |/PARENT/parent.txt     |
      |/welcome.txt           |
      |/simple.odt            |
      |/simple.pdf            |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for files by pattern (not full word - end of word missing)
    Given using <dav_version> DAV path
    When user "user0" searches for "ownC" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/textfile0.txt         |
      |/textfile1.txt         |
      |/textfile2.txt         |
      |/textfile3.txt         |
      |/textfile4.txt         |
      |/PARENT/CHILD/child.txt|
      |/PARENT/parent.txt     |
      |/welcome.txt           |
      |/simple.odt            |
      |/simple.pdf            |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @skip @issue-38
  Scenario Outline: search for files by pattern (not full word - start of word missing)
    Given using <dav_version> DAV path
    When user "user0" searches for "Cloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/textfile0.txt         |
      |/textfile1.txt         |
      |/textfile2.txt         |
      |/textfile3.txt         |
      |/textfile4.txt         |
      |/PARENT/CHILD/child.txt|
      |/PARENT/parent.txt     |
      |/welcome.txt           |
      |/simple.odt            |
      |/simple.pdf            |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @skip @issue-38
  Scenario Outline: search for files by pattern (not full word - only middle part of word given)
    Given using <dav_version> DAV path
    When user "user0" searches for "wnClo" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/textfile0.txt         |
      |/textfile1.txt         |
      |/textfile2.txt         |
      |/textfile3.txt         |
      |/textfile4.txt         |
      |/PARENT/CHILD/child.txt|
      |/PARENT/parent.txt     |
      |/welcome.txt           |
      |/simple.odt            |
      |/simple.pdf            |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for files by pattern (edge case)
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content <file-content> to "/upload-edge-case.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for <search> using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/upload-edge-case.txt |
    Examples:
      | dav_version | file-content | search |
      | old         | "000"        | "000"  |
      | new         | "000"        | "000"  |
      | old         | "000"        | "  0"  |
      | new         | "000"        | "  0"  |
      | old         | "text -1 t"  | " -1"  |
      | new         | "text -1 t"  | " -1"  |
      | old         | "false"      | "false"|
      | new         | "false"      | "false"|
      | old         | "null"       | "null" |
      | new         | "null"       | "null" |

  Scenario Outline: search for files by pattern - pattern matches filename and content
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "this file is uploaded to oC" to "/upload.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for "upload" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for files by pattern - pattern matches filename of one file and content of others
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "files content" to "/ownCloud.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/ownCloud.txt  |
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for files by UTF pattern
    Given using <dav_version> DAV path
    And user "user0" has uploaded file with content "मेरो नेपालि content" to "/utf-upload.txt"
    And user "user0" has uploaded file with content "मेरो दोस्रो नेपालि content" to "/just-a-folder/utf-upload.txt"
    And files of user "user0" have been indexed
    When user "user0" searches for "नेपालि" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/utf-upload.txt              |
      |/just-a-folder/utf-upload.txt|
    And the search result of "user0" should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for deleted files should not work
    Given using <dav_version> DAV path
    When user "user0" deletes file "/upload.txt" using the WebDAV API
    And user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    But the search result of "user0" should not contain these files:
      |/upload.txt                  |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for renamed file
    Given using <dav_version> DAV path
    When user "user0" moves file "/upload.txt" to "/renamed_textfile0.txt" using the WebDAV API
    And the administrator indexes files of user "user0"
    And user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/renamed_textfile0.txt       |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    But the search result of "user0" should not contain these files:
      |/upload.txt                  |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for changed files
    Given using <dav_version> DAV path
    When user "user0" uploads file with content "files with changed content" to "/upload.txt" using the WebDAV API
    And the administrator indexes all files
    And user "user0" searches for "change" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/upload.txt                  |
    And the search result of "user0" should not contain these files:
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: search for changed files, search for content that was in the original file, but not in the new file
    Given using <dav_version> DAV path
    When user "user0" uploads file with content "files with changed content" to "/just-a-folder/uploadÜठिF.txt" using the WebDAV API
    And the administrator indexes all files
    And user "user0" searches for "more" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/फन्नि näme/upload.txt          |
    And the search result of "user0" should not contain these files:
      |/just-a-folder/uploadÜठिF.txt|
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: user should not be able to search in files of other users
    Given using <dav_version> DAV path
    And user "user1" has been created
    And user "user1" has uploaded file with content "files content" to "/user1-upload.txt"
    And all files have been indexed
    When user "user1" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user1" should contain these files:
      |/user1-upload.txt                  |
    But the search result of "user1" should not contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फन्नि näme/upload.txt    |
    Examples:
      | dav_version |
      | old         |
      | new         |

  @local_storage
  Scenario Outline: search on local storage
    Given using <dav_version> DAV path
    And user "user0" has moved file "/upload.txt" to "/local_storage/upload.txt"
    And user "user0" has created a folder "/local_storage/just-a-folder"
    And user "user0" has moved file "/just-a-folder/upload.txt" to "/local_storage/just-a-folder/upload.txt"
    And the administrator indexes files of user "user0"
    And user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/local_storage/upload.txt               |
      |/local_storage/just-a-folder/upload.txt |
      |/just-a-folder/uploadÜठिF.txt            |
      |/फन्नि näme/upload.txt                     |
    But the search result of "user0" should not contain these files:
      |/upload.txt                             |
      |/just-a-folder/upload.txt               |
    Examples:
      | dav_version |
      | old         |
      | new         |