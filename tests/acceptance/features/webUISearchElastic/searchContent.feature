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

  @issue-38
  Scenario: Search content only (not full word - start of word missing)
    When the user searches for "psum" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should not be listed in the search results in the other folders section on the webUI
    #Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
    #  """
    #  This is lorem text in the simple-folder.
    #  """

  @issue-38
  Scenario: Search content only (not full word - only middle part of word given)
    When the user searches for "psu" using the webUI
    Then file "lorem.txt" with path "/simple-folder" should not be listed in the search results in the other folders section on the webUI
    #Then file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI with highlights containing:
    #  """
    #  This is lorem text in the simple-folder.
    #  """

  Scenario: Search pattern matches filename of one file and content of others
    Given user "Brian" has uploaded file with content "content to search for" to "/new-file.txt"
    And user "Brian" has uploaded file with content "does-not-matter" to "/file-to-search-for.txt"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "search" using the webUI
    Then file "file-to-search-for.txt" should be listed on the webUI
    And file "new-file.txt" with path "/" should be listed in the search results in the other folders section on the webUI with highlights containing:
      """
      content to search for
      """
    But file "lorem.txt" should not be listed on the webUI

  Scenario: search for files by UTF pattern
    Given user "Brian" has uploaded file with content "मेरो नेपालि content" to "/utf-upload.txt"
    And user "Brian" has uploaded file with content "मेरो दोस्रो नेपालि content" to "/simple-folder/utf-upload.txt"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    When the user searches for "नेपालि" using the webUI
    Then file "utf-upload.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    And file "utf-upload.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI

  @skipOnFIREFOX @issue-136
  # The last 'But' step fails on Firefox.
  Scenario: search for deleted or renamed file
    When the user deletes file "lorem.txt" using the webUI
    And the user renames file "lorem-big.txt" to "aaa-lorem.txt" using the webUI
    And the search index has been updated
    And the user searches for "ipsum" using the webUI
    Then file "lorem.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "lorem.txt" with path "/simple-folder" should be listed in the search results in the other folders section on the webUI
    And file "lorem-big.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
    But file "aaa-lorem.txt" with path "/" should be listed in the search results in the other folders section on the webUI

  Scenario: search for new content in a overwritten file
    When the user uploads overwriting file "lorem-big.txt" using the webUI
    And the search index has been updated
    And the user searches for "file" using the webUI
    Then file "lorem-big.txt" with path "/" should be listed in the search results in the other folders section on the webUI

  Scenario: search for overwritten file, search for content that was in the original file, but not in the new file
    When the user uploads overwriting file "lorem-big.txt" using the webUI
    And the search index has been updated
    And the user searches for "suspendisse" using the webUI
    Then file "block-aligned.txt" with path "/" should be listed in the search results in the other folders section on the webUI
    But file "lorem-big.txt" with path "/" should not be listed in the search results in the other folders section on the webUI

  Scenario: user should not be able to search in files of other users
    Given user "Carol" has been created with default attributes and skeleton files
    And user "Carol" has uploaded file with content "my secret content" to "/Brian-upload.txt"
    And the search index has been updated
    And the user has reloaded the current page of the webUI
    And the user searches for "secret" using the webUI
    Then file "Brian-upload.txt" should not be listed on the webUI
    Then file "Brian-upload.txt" with path "/" should not be listed in the search results in the other folders section on the webUI
