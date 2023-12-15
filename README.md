# ApprovalBundle

A plugin for [Kimai](https://www.kimai.org/) - a timetracking open source tool - to approve timesheets of users on a weekly basis including APIs.

Checkout the [Documentation](./documentation.md) for content information and [Troubleshooting](./doc_troubleshooting.md) if you have issues.

Here is a short live demo:

![Example process for Teamleads](./_documentation/ApprovalTeamlead.gif)

## Kimai2 Version 1 vs. Version 2

The Plugin as located in the `main` branch, supports the version 1 of Kimai. Version 2 which is live is currently (december 2023) not fully supported, thanks to 
Vitor Mattos and Sam MacLennan which are contributing for this update. The branch for the Kimai version 2 is available in the branch `kimai_v2_update`.

## Requirements

- Requires Kimai 2, V1.16.10 - V1.30.11 (Kimai2 Version 2 is under development by collaborators in branch `kimai_v2_update`) 

Optional but recommended:
- MetaFields plugin - without "overtime" will not be shown unless "Display Overtime" is activated and "Settings workdays" are set for the future )
- LockdownPerUser plugin ([GitHub](https://github.com/kevinpapst/LockdownPerUserBundle)) - without the lockdown functionality will not work

## Features

- Users can send a week for approval (in sequential order)
- User lockdown -> a submitted/approved week can no longer be edited (apart from admins) - there is one lockdown date per user (LockdownPerUserBundle)
- Teamlead/Admin can approve or deny the week
- Overview of approvals, missing approvals and the status
- Mailing options to recall approval tasks if outstanding

## Status

The approval bundle is working pretty well. There had been lately updates to support overtime hours and also store the actual duration along. The bundle is been used and issues received (GitHub issues) are handled. A detailed testing is NOT performed. For this the release is pretty stable, but there might be issues due to different settings, environment etc.

## Issues

It is highly recommended to use the **same timezone** setting for all users. Furthermore all users should use the **same "Start day of the week"** setting - ideally everybody should use "Monday". Otherwise issues could appear as, e.g. Monday times can be located on a Sunday when the teamlead and the user using different timezones. Furthermore the "Start day" is used to store the approval week. When the "Start day" is Sunday for a user and Monday for the teamlead, the approval will not work appropriately.

Please checkout [doc_troubleshooting.md](./doc_troubleshooting.md) for troubleshooting.

## Installation

First unzip the plugin into to your Kimai `plugins` directory:

```bash
unzip ApprovalBundle-x.x.zip -d <kimai path>/var/plugins/
```

And then reload Kimai and install all migrations:

```bash
bin/console kimai:reload
bin/console kimai:bundle:approval:install
```

The plugin should appear now.

## Settings

### Meta-Field Setup (optional)

The ApprovalBundle needs some meta fields and settings to be done. The daily and workly hours are displayed. For this the daily working time per day needs to be specified per user. Typically it might be 8h per week day. But there are very different situations, so someone might only work 4 days a week or less hours a day.

The following meta-fields can be created ([Custom-Field-Plugin](https://www.kimai.org/store/custom-fields-bundle.html) is required for this):

- Custom-Fields -> Users
- The following fields must be from type = "duration", required field, visible, Role = "ROLE_SUPER_ADMIN", default for most should be default = "28800" (which is for 8 hours), for Saturday/Sunday it should be "0" - the names could be anything, but the meaning is according those descriptions
  - Daily working time Monday (daily_working_time_monday)
  - Daily working time Tuesday (daily_working_time_tuesday)
  - Daily working time Wednesday (daily_working_time_wednesday)
  - Daily working time Thursday (daily_working_time_thursday)
  - Daily working time Friday (daily_working_time_friday)
  - Daily working time Saturday (daily_working_time_saturday)
  - Daily working time Sunday (daily_working_time_sunday)

These values must then be set for every active user. Go to System -> Users -> Select each user -> Settings -> edit daily working time if required -> "Save". To see that all users have that these values, you can checkout the overview ("system" -> "user" -> click the "exe" mark "daily working time" to see them all).

### Remark LockdownBundle

The lockdown bundle also comes along with some custom user fields. An empty value is not accepted for start of approval timeframe, please enter "0000-01-01 00:00:01" for the three lockdockdown fields. The same you can enter for the other two time-settings. The ApprovalBundle will modify the "Lockdown period end" and the "Lockdown grace period".

### Team Setup

Next the teams needs to be setup. The teams define which person approves the time for what user. It is typically a picture of the organization. The teamlead is reponsible to approve times from it's team members. A teamlead can also be a member of a different team and for this has also an approver. The super user can perform approvals for all. It is expected that the teamlead has also the role of the teamlead - otherwise he/she cannot see the approvals.

### Approval Settings

The final approval settings can be done via approval -> settings. Please enter the names of the daily_working_time_(day of the week) in the appropriate fields. A customer for off-days can also be set - then break times are not considered for those. The E-Mail link will be used as prefix to have the mails containing the correct links for approval views. You might want to enter something like `https://kimai.example.de/`. The approval week start date defines a date where the approval workflow should start. All prior unapproved weeks are ignored.

Then there are two toggle options available. "Calculate breaktime issues" can be used to deactivate the calculation of breaktime issues. In Germany, there is a law available that sepcific break times must be available. For this in the "Apporoved weeks report" there are red hints when the German worktime rules are not followed, e.g. someone worked 8 hours without an appropriate break. If you are not located in Germany, you can switch it off as this might be irrelevant for you.

The "Display Overtime" options allows to calculate and display expected hours and overtime hours. For example, a colleague is working 40 hours a week. When she is working the concrete week 45 hours, she has 5 hours overtime for that week. The expected hours are to be entered in the "Daily Working time" per day and/or in the "Settings workdays". Remark: if you set all expected working hours to zero, you see the summed up actual working hours. When this option is active, there is also an overview tab for "Overtime" showing the submitted/approved weeks including their times. If you are not interested in "Overtime" at all, then this can be deactivated.

![Screenshot Settings](./_documentation/Screenshot_Settings.png)

### Role Settings

There are two new roles available for the team approval. The `view_team_approval` ideally should be YES for all but the user. This allows up from the teamlead hierarchy to see the approvals of their team. The `view_all_approval` should either be YES for System-Admin only or for System-Admin and Admin, depending on your schema. 

## Functionality of Lockdown (requires LockdownBundle)

With the lockdown bundle the loockdown periods can be set per user and no longer per system option. For this it is possible that user 1 has a lockdown date as of 01.01.2022 whereas user 2 could have for example a lockdown date of 15.01.2022. Per user - a locktime frame can be defined by "Lockdown period start" and "Lockdown period end". Considering also the "Lockdown grace period" (how long after the locktime end it should still be possible to edit time entries) - this defines which time entries can be modified by the user. Please checkout the general lockdown period documentation [here](https://www.kimai.org/documentation/configurations.html#lockdown-period) for detailed information - the same principle is applied, but "per user".

When a week is submitted for approval, then this or any prior week should be locked - no time modifications for that week should be possible. This timesheet lock must be available per user. The following graphic shows an example.

![Example schema for lockdown](./_documentation/ApprovalLockdown.png)

When "User A" has submitted weeks 1-3 for approval, then this user can submit week 4 next and can not modify any times including week 3 or prior. "User B" is a bit slower with week submission - only submitted week 1 and 2 - and has as current lock date the last day of week 2. For this "User B" is able to create/modify/delete time sheets in week 3 and 4.

To have this functionality available, the ApprovalBundle changes the "Lockdown period end" and "Lockdown grace period". Typically both values will be set to the last day/second of the approval end date. In case this date is in the future (for example when submitting weeks which are upcoming) - then the end grace is the current day to prohibit any editing for the future.

## APIs

The following APIs are available. You might want to check out the API swagger documentation within Kimai where the API commands can directly be executed as well.

    Kimai -> click your icon -> settings -> API -> click the book icon top right

## Add to approve API

It's possible to "add to approve" the selected week by API. User is optional - per default the data for the user who submitted the request is provided.

request method: **POST**

url: `{your url address}/api/add_to_approve?user={user ID}&date={monday of selected week: Y-m-d}`

headers:
```
X-AUTH-USER: login
X-AUTH-TOKEN: token/password
```

response:
- response code 200 - URL, to the selected week "added to approval"
- response 400 - "Approval already exists" / "User not from your team"  / "Please add previous weeks to approve"
- response 403 - by bad authentication header
- response 404 - wrong user

Admin can "add to approve" all users.
Teamlead can "add to approve" only users from his team.
Normal users can "add to approve" only their own.

## Week status API

It's possible to check status of selected week. User is optional - per default the data for the user who submitted the request is provided.

request method: **GET**

url: `{your url address}/api/week-status?user={user ID}&date={monday of selected week: Y-m-d}`

headers:
```
X-AUTH-USER: login
X-AUTH-TOKEN: token/password
```

response:
- response code 200 - information about status
- response 400 - "Access denied" / "User not from your team"
- response 403 - by bad authentication header
- response 404 - wrong user

Admin can check the status of all users
Teamlead can check the status of his team users
Normal users can check their status

## Next week API

It's possible to check which week can be currently submitted. User is optional - per default the data for the user who submitted the request is provided.

request method: **GET**

url: `{your url address}/api/next-week?user={user ID}`

headers:
```
X-AUTH-USER: login
X-AUTH-TOKEN: token/password
```

response:

- response code 200 - information about the week
- response 403 - by bad authentication header
- response 404 - wrong user / no data

## Overtime for Year

This will get the overtime for that year considering all submitted/approved approval weeks. User is optional - per default the data for the user who submitted the request is provided.

request method: **GET**

url: `{your url address}/api/overtime_year?user={user ID}&date={monday of selected week: Y-m-d}`

headers:
```
X-AUTH-USER: login
X-AUTH-TOKEN: token/password
```

response:

- response code 200 - information about overtime for that year
- response 403 - by bad authentication header
- response 404 - wrong user / no data

## Overtime on Weekly Bases 

This will get a the overtime on a weekly bases up until that date for the corresponding year (content of "Overtime", but in JSON format). User is optional - per default the data for the user who submitted the request is provided.

request method: **GET**

url: `{your url address}/api/weekly_overtime?user={user ID}&date={monday of selected week: Y-m-d}`

headers:
```
X-AUTH-USER: login
X-AUTH-TOKEN: token/password
```

response:

- response code 200 - information about overtime for that year
- response 403 - by bad authentication header
- response 404 - wrong user / no data

## Cronjobs

Cronjobs can be setup to activate mailings with respect to outstanding approval processes. The following commands are available:

All commands are run with the command:
`bin/console kimai:bundle:approval:{{ command from table }}`
Command send lists of users (without system-admin and disabled users)

E.g.
`bin/console kimai:bundle:approval:admin-not-submitted-users`


|   Command                             |   Email to:                                                   |   Contents                                                                                                                    |
|---------------------------------------|---------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
|   admin-not-submitted-users           |   System-Admin                                                |   List of all users with his 'not submitted' weeks. Command send lists of users (without system-admin and disabled users).    |
|   teamlead-not-submitted-last-week    |   Active team-leaders                                         |   List of team users with his 'not submitted' weeks. Command send lists of users (without system-admin and disabled users).   |
|   user-not-submitted-weeks            |   All active users (without admins and System-Admin)          |   List of weeks that are 'not submitted'.                                                                                     |

## Contribution

Many thanks go to [HMR-IT](https://www.hmr-it.de) which had been highly involved in this project.

Additional thanks go to Milo Ivir for additional translations and to Kevin Papst for code enhancements and the update to use this bundle with less pre-requisites.

Furthermore thanks to Vitor Mattos and Sam MacLennan which are working on the migration for the ApprovalBundle supporting Kimai Version 2.