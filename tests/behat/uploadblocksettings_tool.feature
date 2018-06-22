@admin @uon @tool_uploadblocksettings
Feature: Setting course blocks by uploading a CSV file.
    In order to standardise default blocks across courses
    As an administrator
    I need to be able to upload a csv file that sets blocks in a course

    Background:
        Given the following "courses" exist:
            | fullname | shortname  | category | idnumber |
            | Course 1 | C101       | 1        | idnum1   |
            | Course 2 | C102       | 1        | idnum2   |
        Given the following "users" exist:
            | username    | firstname | lastname | email                |
            | student1    | Sam       | Student  | student1@example.com |
            | teacher1    | Teacher   | One      | teacher1@example.com |
        Given the following "course enrolments" exist:
            | user        | course | role           |
            | student1    | C101   | student        |
            | teacher1    | C101   | editingteacher |
   
    @_file_upload
    Scenario: Manager can upload a CSV file using the upload block settings plugin
        When I log in as "admin"
        And I navigate to "Upload block settings" node in "Site administration > Courses"
        And I upload "admin/tool/uploadblocksettings/tests/fixtures/blocksettings_test.csv" file to "File" filemanager
        And I click on "id_submitbutton" "button"
        And I follow "Courses"
        And I follow "Course 1"
        Then I should see "Calendar"
        And I should not see "Participants"