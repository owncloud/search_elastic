@api
Feature: Limit access to groups
As an administrator
I would like to be able to limit the access to search_elastic to specific groups
So that the server is not overloaded

  Background:
    Given these users have been created with default attributes:
      | username |
      | user0    |
      | user1    |
    And user "user0" has uploaded file with content "files content" to "/ownCloud.txt"
    And user "user1" has uploaded file with content "files content" to "/ownCloud.txt"
    And group "grp1" has been created
    And user "user0" has been added to group "grp1"
    And all files have been indexed

  @issue-43
  Scenario Outline: limit search_elastic access to a group
    Given using <dav_version> DAV path
    When the administrator limits the access to search_elastic to "grp1"
    And all files have been indexed
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should contain these files:
    And the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    And the search result should contain these files:
      |/ownCloud.txt  |
    When user "user1" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    #And the search result should not contain these files:
    And the search result should contain these files:
      |/ownCloud.txt  |
    But the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: limit a group to only search in metadata
    Given using <dav_version> DAV path
    When the administrator disables the full text search for "grp1"
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/ownCloud.txt  |
    But the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    When user "user1" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
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

  Scenario Outline: limit a group to only search in metadata (reindex after limitation is set)
    Given using <dav_version> DAV path
    When the administrator disables the full text search for "grp1"
    And the administrator indexes all files
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/ownCloud.txt  |
    And the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    When user "user1" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
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

  Scenario Outline: limit multiple groups to only search in metadata
    Given using <dav_version> DAV path
    And user "user2" has been created with default attributes
    And user "user2" has uploaded file with content "files content" to "/ownCloud.txt"
    And group "grp2" has been created
    And user "user2" has been added to group "grp2"
    When the administrator disables the full text search for "grp1,grp2"
    And the administrator indexes all files
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/ownCloud.txt  |
    But the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    When user "user2" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/ownCloud.txt  |
    But the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    When user "user1" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
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

  Scenario Outline: limit a group to only search in metadata, the searching user is member in a limited and also in an unlimited group
    Given using <dav_version> DAV path
    And group "grp2" has been created
    And user "user0" has been added to group "grp2"
    When the administrator disables the full text search for "grp1"
    And user "user0" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result should contain these files:
      |/ownCloud.txt  |
    But the search result should not contain these files:
      |/textfile0.txt |
      |/textfile1.txt |
      |/textfile2.txt |
      |/textfile3.txt |
      |/textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
