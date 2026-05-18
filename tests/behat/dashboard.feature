@block @block_grade_me @block_grade_me_dashboard
Feature: Teacher Dashboard page is accessible to graders
    In order to triage my pending grading workload
    As a teacher
    I want to open the Teacher Dashboard from a URL or from the block.

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

    Scenario: A teacher can open the Teacher Dashboard
        When I log in as "teacher1"
        And I visit "/blocks/grade_me/dashboard.php"
        Then I should see "Open Teacher Dashboard"
        And I should see "Active courses"
        And I should see "Median wait"

    Scenario: A student is denied access to the dashboard
        When I log in as "student1"
        And I visit "/blocks/grade_me/dashboard.php"
        Then I should see "Sorry, but you do not currently have permissions to do that"
