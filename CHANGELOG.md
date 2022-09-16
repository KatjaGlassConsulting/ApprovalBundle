# Changelog

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