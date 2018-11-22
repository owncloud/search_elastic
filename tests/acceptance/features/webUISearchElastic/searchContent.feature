@webUI @insulated @disablePreviews
Feature: Search

As a user
I would like to be able to search for the content of files
So that I can find needed files quickly

  Background:
    Given these users have been created:
    |username|password|displayname|email       |
    |user1   |1234    |User One   |u1@oc.com.np|
    And files of user "user1" have been indexed
    And the user has browsed to the login page
    And the user has logged in with username "user1" and password "1234" using the webUI

  Scenario: Simple search
    When the user searches for "lorem" using the webUI
    Then file "lorem.txt" should be listed on the webUI
    And file "lorem-big.txt" should be listed on the webUI
    And file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI
    And file "lorem-big.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI
    And file "lorem.txt" with path "/0" should be listed in the search results in the other folders section on the webUI
    And file "lorem.txt" with path "/strängé नेपाली folder" should be listed in the search results in the other folders section on the webUI
    And file "lorem-big.txt" with path "/strängé नेपाली folder" should be listed in the search results in the other folders section on the webUI

  Scenario: Search content only
    When the user searches for "ipsum" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search content only (not exact case)
    When the user searches for "iPsUM" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search content only (not full word - end of word missing)
    When the user searches for "ipsu" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  @skip @issue-38
  Scenario: Search content only (not full word - start of word missing)
    When the user searches for "psum" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  @skip @issue-38
  Scenario: Search content only (not full word - only middle part of word given)
    When the user searches for "psu" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search pattern matches filename of one file and content of others
    Given user "user1" has uploaded file with content "content to search for" to "/new-file.txt"
    And user "user1" has uploaded file with content "does-not-matter" to "/file-to-search-for.txt"
    And files of user "user1" have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "search" using the webUI
    Then file "file-to-search-for.txt" should be listed on the webUI
    And file "new-file.txt" with path "/" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      content to search for
      """ 
    But file "lorem.txt" should not be listed on the webUI

  Scenario: search for files by UTF pattern
    Given user "user1" has uploaded file with content "मेरो नेपालि content" to "/utf-upload.txt"
    And user "user1" has uploaded file with content "मेरो दोस्रो नेपालि content" to "/simple-folder/utf-upload.txt"
    And files of user "user1" have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "नेपालि" using the webUI
    Then file "utf-upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "utf-upload.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI

  Scenario: search for deleted or renamed file
    When the user deletes file "lorem.txt" using the webUI
    And the user renames file "lorem-big.txt" to "aaa-lorem.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "ipsum" using the webUI
    Then file "lorem.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI
    And file "lorem-big.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "aaa-lorem.txt" with path "/" should be listed in the search results in the other folders section on the webUI

  Scenario: search for new content in a overwritten file
    When the user uploads overwriting file "lorem-big.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "file" using the webUI
    Then file "lorem-big.txt" with path "/" should be listed in the search results in the other folders section on the webUI

  Scenario: search for overwritten file, search for content that was in the original file, but not in the new file
    When the user uploads overwriting file "lorem-big.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "suspendisse" using the webUI
    Then file "block-aligned.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    But file "lorem-big.txt" with path "/" should not be listed in the search results in the other folders section on the webUI

  Scenario: user should not be able to search in files of other users
    Given user "user2" has been created with default attributes
    And user "user2" has uploaded file with content "my secret content" to "/user1-upload.txt"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    And the user searches for "secret" using the webUI
    Then file "user1-upload.txt" should not be listed on the webUI
    Then file "user1-upload.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
