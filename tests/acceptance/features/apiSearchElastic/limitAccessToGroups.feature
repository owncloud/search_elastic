@api
Feature: Limit access to groups
As an administrator
I would like to be able to limit the access to search_elastic to specific groups
So that the server is not overloaded

  Background:
    Given user "user0" has been created
    And user "user0" has uploaded file with content "files content" to "/ownCloud.txt"
    And user "user1" has been created
    And user "user1" has uploaded file with content "files content" to "/ownCloud.txt"
    And group "grp1" has been created
    And user "user0" has been added to group "grp1"
    And all files have been indexed

  @skip @issue-43
  Scenario Outline: limit full text search to a certain group
    Given using <dav_version> DAV path
    When the administrator limits the access to search_elastic to "grp1"
    And all files have been indexed
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user0" should contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
      |/ownCloud.txt  |
    When user "user1" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of "user1" should contain these files:
      |/ownCloud.txt  |
    But the search result of "user1" should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
