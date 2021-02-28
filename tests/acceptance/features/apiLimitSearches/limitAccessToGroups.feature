@api
Feature: Limit access to groups
  As an administrator
  I would like to be able to limit the access to search_elastic to specific groups
  So that the server is not overloaded

  Background:
    Given these users have been created with default attributes and small skeleton files:
      | username |
      | Alice    |
      | Brian    |
    And user "Alice" has uploaded file with content "files content" to "/ownCloud.txt"
    And user "Brian" has uploaded file with content "files content" to "/ownCloud.txt"
    And group "grp1" has been created
    And user "Alice" has been added to group "grp1"
    And the search index has been created

  Scenario Outline: limit search_elastic access to a group
    Given using <dav_version> DAV path
    When the administrator limits the access to search_elastic to "grp1"
    And the search index has been updated
    And user "Alice" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
      | /ownCloud.txt  |
    When user "Brian" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /ownCloud.txt |
    But the search result of user "Brian" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: limit a group to only search in metadata
    Given using <dav_version> DAV path
    When the administrator disables the full text search for "grp1"
    And user "Alice" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /ownCloud.txt |
    But the search result of user "Alice" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    When user "Brian" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /ownCloud.txt  |
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: limit a group to only search in metadata (reindex after limitation is set)
    Given using <dav_version> DAV path
    When the administrator disables the full text search for "grp1"
    And the search index has been updated
    And user "Alice" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /ownCloud.txt |
    And the search result of user "Alice" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    When user "Brian" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /ownCloud.txt  |
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: limit multiple groups to only search in metadata
    Given using <dav_version> DAV path
    And user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has uploaded file with content "files content" to "/ownCloud.txt"
    And group "grp2" has been created
    And user "Carol" has been added to group "grp2"
    When the administrator disables the full text search for "grp1,grp2"
    And the search index has been updated
    And user "Alice" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /ownCloud.txt |
    But the search result of user "Alice" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    When user "Carol" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Carol" should contain these files:
      | /ownCloud.txt |
    But the search result of user "Carol" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    When user "Brian" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Brian" should contain these files:
      | /ownCloud.txt  |
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |

  Scenario Outline: limit a group to only search in metadata, the searching user is member in a limited and also in an unlimited group
    Given using <dav_version> DAV path
    And group "grp2" has been created
    And user "Alice" has been added to group "grp2"
    When the administrator disables the full text search for "grp1"
    And user "Alice" searches for "ownCloud" using the WebDAV API
    Then the HTTP status code should be "207"
    And the search result of user "Alice" should contain these files:
      | /ownCloud.txt |
    But the search result of user "Alice" should not contain these files:
      | /textfile0.txt |
      | /textfile1.txt |
      | /textfile2.txt |
      | /textfile3.txt |
      | /textfile4.txt |
    Examples:
      | dav_version |
      | old         |
      | new         |
