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
    When the user searches for "lorem text" using the webUI
    And the file "lorem.txt" with the path "/simple-folder" should be listed in the search results in other folders section on the webUI with highlights containing:
      """
      This is lorem text in the simple-folder.
      """
