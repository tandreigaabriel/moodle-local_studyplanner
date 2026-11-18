PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> vendor\bin\phpcbf.bat --standard=moodle --extensions=php --ignore=_/vendor/_ C:\laragon\www\moodle\local\studyplanner

## PHPCBF RESULT SUMMARY

## FILE FIXED REMAINING

## ...\www\moodle\local\studyplanner\classes\privacy\provider.php 29 1

## A TOTAL OF 29 ERRORS WERE FIXED IN 1 FILE

Time: 3.04 secs; Memory: 16MB

PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> vendor\bin\phpcs.bat --standard=moodle --extensions=php --ignore=_/vendor/_ C:\laragon\www\moodle\local\studyplanner

## FILE: ...\laragon\www\moodle\local\studyplanner\classes\form\personal_task_form.php

## FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE

## 44 | WARNING | Line exceeds 132 characters; contains 136 characters

## FILE: C:\laragon\www\moodle\local\studyplanner\classes\output\today_page.php

## FOUND 1 ERROR AFFECTING 1 LINE

41 | ERROR | No one-line description found in phpdocs for docblock of function
| | \_\_construct

---

## FILE: C:\laragon\www\moodle\local\studyplanner\classes\output\week_page.php

## FOUND 1 ERROR AFFECTING 1 LINE

42 | ERROR | No one-line description found in phpdocs for docblock of function
| | \_\_construct

---

## FILE: C:\laragon\www\moodle\local\studyplanner\classes\privacy\provider.php

## FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE

## 46 | WARNING | Line exceeds 132 characters; contains 162 characters

## FILE: C:\laragon\www\moodle\local\studyplanner\lib.php

## FOUND 0 ERRORS AND 1 WARNING AFFECTING 1 LINE

25 | WARNING | Unexpected MOODLE_INTERNAL check. No side effects or multiple
| | artifacts detected.

---

Time: 2.13 secs; Memory: 14MB

PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci phpdoc C:\laragon\www\moodle\local\studyplanner --moodle C:\laragon\www\moodle --max-warnings=0
RUN Moodle PHPDoc Checker on local_studyplanner
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci validate C:\laragon\www\moodle\local\studyplanner --moodle C:\laragon\www\moodle
RUN Validating local_studyplanner

> Found required file: version.php
> Found required file: lang/en/local_studyplanner.php
> ! Skipping validation of missing or optional file: db/upgrade.php
> In lang/en/local_studyplanner.php, found language pluginname
> X In db/install.xml, failed to find table prefixes local_studyplanner
> PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
> PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main> php bin\moodle-plugin-ci mustache C:\laragon\www\moodle\local\studyplanner --moodle C:\laragon\www\moodle
> RUN Mustache Lint on local_studyplanner
> 'env' is not recognized as an internal or external command,
> operable program or batch file.
> 'env' is not recognized as an internal or external command,
> operable program or batch file.
> PS C:\laragon\www\moodle-plugin-ci-main\moodle-plugin-ci-main>
