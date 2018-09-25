@api
Feature: Search
As a user
I would like to be able to search for the content of files
So that I can find needed files quickly

  Background:
    Given user "user0" has been created
    And user "user0" has created a folder "/just-a-folder"
    And user "user0" has created a folder "/फनी näme"
    And user "user0" has uploaded file with content "files content" to "/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/a-image.png"
    And user "user0" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/just-a-folder/a-image.png"
    And user "user0" has uploaded file with content "more content" to "/just-a-folder/uploadÜठिF.txt"
    And user "user0" has uploaded file with content "and one more content" to "/फनी näme/upload.txt"
    And user "user0" has uploaded file with content "does-not-matter" to "/फनी näme/a-image.png"
    And files of user "user0" have been indexed

  Scenario Outline: search for files by pattern
    Given using <dav_version> DAV path
    When user "user0" searches for "content" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/upload.txt                  |
      |/just-a-folder/upload.txt    |
      |/just-a-folder/uploadÜठिF.txt|
      |/फनी näme/upload.txt    |
    But the search result of "user0" should not contain these files:
      |/a-image.png                 |
    Examples:
      | dav_version |
      | old         |
      | new         |