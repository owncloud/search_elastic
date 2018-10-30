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
    Then the file "lorem.txt" should be listed on the webUI
    And the file "lorem-big.txt" should be listed on the webUI
    And the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI
    And the file "lorem-big.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI
    And the file "lorem.txt" with the path "/0" should be listed in the search results in other folders section on the webUI
    And the file "lorem.txt" with the path "/strängé नेपाली folder" should be listed in the search results in other folders section on the webUI
    And the file "lorem-big.txt" with the path "/strängé नेपाली folder" should be listed in the search results in other folders section on the webUI

  Scenario: Search content only
    When the user searches for "ipsum" using the webUI
    Then the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search content only (not exact case)
    When the user searches for "iPsUM" using the webUI
    Then the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search content only (not full word - end of word missing)
    When the user searches for "ipsu" using the webUI
    Then the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  @skip @issue-38
  Scenario: Search content only (not full word - start of word missing)
    When the user searches for "psum" using the webUI
    Then the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  @skip @issue-38
  Scenario: Search content only (not full word - only middle part of word given)
    When the user searches for "psu" using the webUI
    Then the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """

  Scenario: Search pattern matches filename of one file and content of others
    Given user "user1" has uploaded file with content "content to search for" to "/new-file.txt"
    And user "user1" has uploaded file with content "does-not-matter" to "/file-to-search-for.txt"
    And files of user "user1" have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "search" using the webUI
    Then the file "file-to-search-for.txt" should be listed on the webUI
    And the file "new-file.txt" with the path "/" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      content to search for
      """ 
    But the file "lorem.txt" should not be listed on the webUI

  Scenario: search for files by UTF pattern
    Given user "user1" has uploaded file with content "मेरो नेपालि content" to "/utf-upload.txt"
    And user "user1" has uploaded file with content "मेरो दोस्रो नेपालि content" to "/simple-folder/utf-upload.txt"
    And files of user "user1" have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "नेपालि" using the webUI
    Then the file "utf-upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "utf-upload.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI

  Scenario: search for deleted or renamed file
    When the user deletes the file "lorem.txt" using the webUI
    And the user renames the file "lorem-big.txt" to "aaa-lorem.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "ipsum" using the webUI
    Then the file "lorem.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI
    And the file "lorem-big.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "aaa-lorem.txt" with the path "/" should be listed in the search results in other folders section on the webUI

  Scenario: search for new content in a overwritten file
    When the user uploads overwriting the file "lorem-big.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "file" using the webUI
    Then the file "lorem-big.txt" with the path "/" should be listed in the search results in other folders section on the webUI

  Scenario: search for overwritten file, search for content that was in the original file, but not in the new file
    When the user uploads overwriting the file "lorem-big.txt" using the webUI
    And the administrator indexes files of user "user1"
    And the user searches for "suspendisse" using the webUI
    Then the file "block-aligned.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    But the file "lorem-big.txt" with the path "/" should not be listed in the search results in other folders section on the webUI

  Scenario: user should not be able to search in files of other users
    Given user "user2" has been created
    And user "user2" has uploaded file with content "my secret content" to "/user1-upload.txt"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    And the user searches for "secret" using the webUI
    Then the file "user1-upload.txt" should not be listed on the webUI
    Then the file "user1-upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: user searches for files shared to him as a single user
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files shared to him as a single user (files have been indexed only after sharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: user searches for files shared to him as a member of a group
    Given user "user2" has been created
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files shared to him as a member of a group (files have been indexed only after sharing)
    Given user "user2" has been created
    And group "grp1" has been created
    And user "user1" has been added to group "grp1"
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: unshared files should not be searched
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after sharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  Scenario: unshared files should not be searched (files have been indexed only after unsharing)
    Given user "user2" has been created
    And user "user2" has created a folder "/just-a-folder"
    And user "user2" has uploaded file with content "files content" to "/upload.txt"
    And user "user2" has uploaded file with content "files content" to "/upload-keep.txt"
    And user "user2" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared file "upload-keep.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And the user has reloaded the current page of the webUI
    When the user unshares the file "upload.txt" using the webUI
    And the user unshares the folder "just-a-folder" using the webUI
    And the administrator indexes all files
    And the user searches for "content" using the webUI
    Then the file "upload-keep.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/" should not be listed in the search results in other folders section on the webUI
    But the file "upload.txt" with the path "/just-a-folder" should not be listed in the search results in other folders section on the webUI

  @skip @issue-36
  Scenario: user searches for files re-shared to him
    Given user "user2" has been created
    And user "user3" has been created
    And user "user3" has created a folder "/just-a-folder"
    And user "user3" has uploaded file with content "files content" to "/upload.txt"
    And user "user3" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And all files have been indexed
    And user "user3" has shared file "upload.txt" with user "user2"
    And user "user3" has shared folder "just-a-folder" with user "user2"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI

  Scenario: user searches for files re-shared to him (files have been indexed only after second sharing)
    Given user "user2" has been created
    And user "user3" has been created
    And user "user3" has created a folder "/just-a-folder"
    And user "user3" has uploaded file with content "files content" to "/upload.txt"
    And user "user3" has uploaded file with content "file with content in subfolder" to "/just-a-folder/upload.txt"
    And user "user3" has shared file "upload.txt" with user "user2"
    And user "user3" has shared folder "just-a-folder" with user "user2"
    And user "user2" has shared file "upload.txt" with user "user1"
    And user "user2" has shared folder "just-a-folder" with user "user1"
    And all files have been indexed
    And the user has reloaded the current page of the webUI
    When the user searches for "content" using the webUI
    Then the file "upload.txt" with the path "/" should be listed in the search results in other folders section on the webUI
    And the file "upload.txt" with the path "/just-a-folder" should be listed in the search results in other folders section on the webUI
    