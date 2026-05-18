@block @block_grade_me @javascript @block_grade_me_responsiveness
Feature: Grading Responsiveness section appears in the block
    In order to monitor grading turnaround
    As a teacher
    I want the Grading Responsiveness section to render below the activity list
    in the Grade Me block.

    Background:
        Given the grade me block is present on all pages.
        And the following "users" exist:
          | username | firstname | lastname | email |
          | teacher1 | T | Eacher | teacher1@example.com |
          | student1 | S | One | student1@example.com |
        And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1 | 0 |
        And the following "course enrolments" exist:
          | user | course | role |
          | teacher1 | C1 | editingteacher |
          | student1 | C1 | student |
        And the following "groups" exist:
          | name | course | idnumber |
          | Group A | C1 | ga |
        And the following "group members" exist:
          | user | group |
          | teacher1 | ga |
          | student1 | ga |
        And the following config values are set as admin:
          | config | value |
          | block_grade_me_enableassign | 1 |
          | block_grade_me_show_responsiveness | 1 |

    Scenario: The responsiveness section header is visible to a teacher
        Given the following "activities" exist:
          | activity | name | intro | course | idnumber | submissiondrafts | assignsubmission_onlinetext_enabled |
          | assign   | A1   | text  | C1     | a1       | 0                | 1                                   |
        When I log in as "teacher1"
        And I am on "Course 1" course homepage
        Then I should see "Grading Responsiveness" in the "Grade Me" "block"
        And "section#grademe-responsiveness" "css_element" should exist
