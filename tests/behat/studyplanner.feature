@local @local_studyplanner
Feature: Students use their study planner
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | One | student1@example.com |
    And the following config values are set as admin:
      | enabled | 1 | local_studyplanner |
    And I log in as "student1"

  Scenario: A student adds a personal task
    Given I visit "/local/studyplanner/personaltasks.php"
    When I follow "Add a personal task"
    And I set the field "Title" to "Read a chapter"
    And I set the field "Estimated minutes" to "45"
    And I press "Save changes"
    Then I should see "Read a chapter"
    When I follow "Edit"
    And I set the field "Title" to "Read two chapters"
    And I press "Save changes"
    Then I should see "Read two chapters"
    When I follow "Delete"
    And I press "Delete"
    Then I should not see "Read two chapters"

  Scenario: A student sees the weekly plan
    Given I visit "/local/studyplanner/week.php"
    Then I should see "This week"
