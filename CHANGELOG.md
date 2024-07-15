# Changelog

## 1.0.2 to 1.0.3

- fix: History of approvals could not have been seen by users
- fix: badge for teamlead
- performance update: remove badge for admin
- deny reason for auto-deny-laters includes additionally date of denied approval "Reset due to earlier approval cancellation"
- deny reason for approval reset set to "Approval reset by Admin"
- declaration updates to support PHP 7.4

## 1.0.1 to 1.0.2

- include check for valid timezones and take user timezone if lockdown-timezone is invalid
- update migration SQL to include additional group to support updates also on SQL option "only_full_group_by"

## 1.0.0 to 1.0.1

- performance updates
- fix migration class issue
- new option to allow system-administrators to be included in approvals
- new option to allow teamlead to self-approve the own weeks

## 0.9.12 to 1.0.0

- fix "overtime" invalid calculation (sunday is ignored) when new workday settings are done
- main version update as in new branch for Kimai V1 support

## 0.9.11 to 0.9.12

- 6h-no-break-check corrected
- new overtime by all report with select date option

## 0.9.10 to 0.9.11

- manual overtime adoption is now also considered for the "week_by_user" view when the approval is performed on

## 0.9.9 to 0.9.10

- Teamlead does not see the own entry for "to approval" tab
- Users can see the overtime also without being teamlead or admin
- Teamlead (according role) having no team, see only tabs with the own entries
- Settings for workdays - entries can now also be deleted (recalculation of vacation/holidays/hours done)
- Bugfix: Overtime storage along approvals corrected - sunday working times had been excluded before
- new overtime settings to allow manual +/- updates

## 0.9.8 to 0.9.9

- Break worktime check updates
  - check to not work on Sundays
  - check to not work of days off
  - check to not work more than 6 hours at a row without 30 minute break

## 0.9.7 to 0.9.8

- Documentation update
- Default Role "view_team_approval" for Teamleads
- Update: on updated working weekdays, update days-off-hours timesheets beginning with last-year on 1st Jan (not older)
- Fix: for teamlead view on teammembers, "expected" is no longer using the hours from the "first-in-list", but the selected user
- Fix: MetaFieldSettings-Issue - return 0 if not available for this user
- Fix: on updated working weekdays, update first days-off-hours and then recalculate approval expected/actual hours
- Fix: enhanced null handling

## 0.9.6 to 0.9.7

- enhance overtime display & API overtime overview
- use display name for user selections
- new setting "Calculate breaktime issues" to allow to disable the breaktime issue calculation and display (German law has different rules for breaks)
- added security checks for overtime display
- overview of weeks with approval has new category "future weeks", for this "current week" only displays the last finished week (previous Monday weeks)

## 0.9.5 to 0.9.6

- new overtime handling
  - new option under settings "Display Overtime"
  - if active - new tab "Overtime" to display the approval weeks including expected and actual hours
  - if active - new tab "Setting Workdays" to allow different daily working hours on specific shift dates, for example when an employee works 40 hours and beginning with a specific time frame 20 hours
  - new API endpoint "/overtime_year" to get overtime for a date starting with the beginning of the year
- fix for Kimai 1.30 "mixed" caused an issue
- fix BreakTimeCheck on Null issue

## 0.9.4 to 0.9.5

- fix access bug caused by using user-display-name on weekly report selection

## 0.9.3 to 0.9.4

- new CI tools
- A new interface and service to use the bundle WITHOUT meta-fields and daily working times - fixes Approval without duration #4
- Code style and fixes
- display user-alias when available, otherwise use username
- update translations - remove [ccnet-test2]

## 0.9.2 to 0.9.3

- error handling -> when the end date for break time is not available, don't through an error but give a user error message
- usort optimization

## 0.9.1 to 0.9.2

- New setting "Approval Start Date", only weeks with this date or later are considered for approval listing/workflow
- Enhanced E-Mail text
- New warning if Sunday is used as user-setting (in per-user screen, if user or displayed user use this not supported setting)
- Fix: current week display contains now also the last week also on Monday of the following week and not only when it is already Tuesday
- Fix: email if month closed: only if it is a past month and also include the very first week which might start in the previous month

## 0.9 to 0.9.1

- enhance tooltips
- when a week is set to no approval, all later weeks which had been submitted or approved are reset to open
- enhance documentation