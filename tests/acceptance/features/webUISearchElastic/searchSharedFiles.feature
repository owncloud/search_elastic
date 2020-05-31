@webUI @insulated @disablePreviews
Feature: Search

  As a user
  I would like to be able to search for the content of files
  So that I can find needed files quickly

  Background:
    Given these users have been created with skeleton files:
      | username | password | displayname  | email             |
      | Brian    | 1234     | Brian Murphy | brian@example.org |
    And the search index has been created
    And the user has browsed to the login page
    And the user has logged in with username "Brian" and password "1234" using the webUI

  Scenario: user searches for files shared to him as a single user
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And the search index has been updated
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: user searches for files shared to him as a single user (files have been indexed only after sharing)
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: user searches for files shared to him as a member of a group
    Given user "Carol" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And the search index has been updated
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: user searches for files shared to him as a member of a group (files have been indexed only after sharing)
    Given user "Carol" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: unshared files should not be searched
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And the search index has been updated
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared file "upload-keep.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user unshares file "upload.txt" using the webUI
    And the user unshares folder "just-a-folder" using the webUI
    And the search index has been updated
    And the user searches for "content" using the webUI
    Then file "upload-keep.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "upload.txt" with path "/just-a-folder" should not be listed in the search results in the other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after sharing)
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared file "upload-keep.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user unshares file "upload.txt" using the webUI
    And the user unshares folder "just-a-folder" using the webUI
    And the search index has been updated
    And the user searches for "content" using the webUI
    Then file "upload-keep.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "upload.txt" with path "/just-a-folder" should not be listed in the search results in the other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after unsharing)
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has created folder "/just-a-folder"
    And user "Carol" has uploaded file with content "files content" to "/upload.txt"
    And user "Carol" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "Carol" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared file "upload-keep.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the user has reloaded the current page of the webUI
    When the user unshares file "upload.txt" using the webUI
    And the user unshares folder "just-a-folder" using the webUI
    And the search index has been updated
    And the user searches for "content" using the webUI
    Then file "upload-keep.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "upload.txt" with path "/just-a-folder" should not be listed in the search results in the other folders section on the webUI

  Scenario: user searches for files re-shared to him
    Given user "Carol" has been created with default attributes and skeleton files
    And user "David" has been created with default attributes and skeleton files
    And user "David" has created folder "/just-a-folder"
    And user "David" has uploaded file with content "files content" to "/upload.txt"
    And user "David" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And the search index has been updated
    And user "David" has shared file "upload.txt" with user "Carol"
    And user "David" has shared folder "just-a-folder" with user "Carol"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: user searches for files re-shared to him (files have been indexed only after second sharing)
    Given user "Carol" has been created with default attributes and skeleton files
    And user "David" has been created with default attributes and skeleton files
    And user "David" has created folder "/just-a-folder"
    And user "David" has uploaded file with content "files content" to "/upload.txt"
    And user "David" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "David" has shared file "upload.txt" with user "Carol"
    And user "David" has shared folder "just-a-folder" with user "Carol"
    And user "Carol" has shared file "upload.txt" with user "Brian"
    And user "Carol" has shared folder "just-a-folder" with user "Brian"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then file "upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "upload.txt" with path "/just-a-folder" should be listed in the search results in the other folders section on the webUI