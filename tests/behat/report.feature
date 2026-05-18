@block @block_grade_me @block_grade_me_report
Feature: Detailed Report page opens for a teacher's group
    In order to drill into a class's pending grading
    As a teacher
    I want to open the Detailed Report with a pre-filtered group.

    Background:
        Given the following "users" exist:
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

    Scenario: A teacher can open the Detailed Report for a group they teach
        When I log in as "teacher1"
        And I visit the responsiveness report for group "ga"
        Then I should see "Pending Grading"
        And I should see "Group A"
        And I should see "All"
