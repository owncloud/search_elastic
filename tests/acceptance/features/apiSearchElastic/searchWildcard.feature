@api
Feature: Search for content using wildcard in query
  As a user
  I would like to be able to search for the content of files
  So that I can find needed files quickly

  Background:
    Given user "Alice" has been created with default attributes and small skeleton files
    And user "Alice" has created folder "/simple-folder"
    And user "Alice" has created folder "/simple-search-folder"
    And user "Alice" has uploaded file with content "foo" to "/file1.txt"
    And user "Alice" has uploaded file with content "bar" to "/file2.txt"
    And user "Alice" has uploaded file with content "baz" to "/file3.txt"
    And user "Alice" has uploaded file with content "foo bar" to "/file4.txt"
    And user "Alice" has uploaded file with content "foo baz" to "/simple-folder/file5.txt"
    And user "Alice" has uploaded file with content "foo baz bar" to "/simple-folder/file6.txt"
    And user "Alice" has uploaded file with content "bar foo baz" to "/simple-search-folder/file7.txt"


  Scenario Outline: search using wildcard pattern at the end of string
    Given using <dav_version> DAV path
    And the search index has been updated
    When user "Alice" searches for "foo*" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain only these files:
      | /file1.txt |
      | /file4.txt |
      | /simple-folder/file5.txt |
      | /simple-folder/file6.txt |
      | /simple-search-folder/file7.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |


  Scenario Outline: search using wildcard pattern in the middle of string
    Given using <dav_version> DAV path
    And the search index has been updated
    When user "Alice" searches for "*bar*" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain only these files:
      | /file2.txt |
      | /file4.txt |
      | /simple-folder/file6.txt |
      | /simple-search-folder/file7.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |


  Scenario Outline: search using wildcard pattern in the beginning of string
    Given using <dav_version> DAV path
    And the search index has been updated
    When user "Alice" searches for "*baz" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain only these files:
      | /file3.txt |
      | /simple-folder/file5.txt |
      | /simple-folder/file6.txt |
      | /simple-search-folder/file7.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |


  Scenario Outline: search using wildcard pattern only
    Given using <dav_version> DAV path
    And the search index has been updated
    When user "Alice" searches for "*" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /file1.txt |
      | /file2.txt |
      | /file3.txt |
      | /file4.txt |
      | /simple-folder/file5.txt |
      | /simple-folder/file6.txt |
      | /simple-search-folder/file7.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |


  Scenario Outline: search using multiple wildcard patterns
    Given using <dav_version> DAV path
    And the search index has been updated
    When user "Alice" searches for "*foo* *baz*" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain only these files:
      | /file1.txt |
      | /file3.txt |
      | /file4.txt |
      | /simple-folder/file5.txt |
      | /simple-folder/file6.txt |
      | /simple-search-folder/file7.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
