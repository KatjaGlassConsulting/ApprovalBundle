# ApprovalBundle

A plugin for [Kimai](https://www.kimai.org/) - a timetracking open source tool - to approve timesheets of users on a weekly basis including APIs.

Checkout the [Documentation](./documentation.md) for content information.

Here is a short live demo:

![Example process for Teamleads](./_documentation/ApprovalTeamlead.gif)

## Requirements

- Requires Kamai 2, V1.16.10 or higher
- MetaFields plugin
- LockdownPerUser plugin ([GitHub](https://github.com/kevinpapst/LockdownPerUserBundle)) 

## Features

- Users can send a week for approval (in sequential order)
- User lockdown -> a submitted/approved week can no longer be edited (apart from admins) - there is one lockdown date per user (LockdownPerUserBundle)
- Teamlead/Admin can approve or deny the week
- Overview of approvals, missing approvals and the status
- Mailing options to recall approval tasks if outstanding

## Status

The approval bundle is already working pretty well. Some updates will be done soon, as some functionality and checks are not final yet. Unless these things are implemented the version is below 1.

## Issues

It is highly recommended to use the **same timezone** setting for all users. Furthermore all users should use the **same "Start day of the week"** setting - ideally everybody should use "Monday". Otherwise issues could appear as, e.g. Monday times can be located on a Sunday when the teamlead and the user using different timezones. Furthermore the "Start day" is used to store the approval week. When the "Start day" is Sunday for a user and Monday for the teamlead, the approval will not work appropriately.

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

## APIs

The following APIs are available. You might want to check out the API swagger documentation within Kimai where the API commands can directly be executed as well.

    Kimai -> click your icon -> settings -> API -> click the book icon top right

## Add to approve API

It's possible to "add to approve" the selected week by API.

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

It's possible to check status of selected week

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

It's possible to check which week can be currently submitted

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