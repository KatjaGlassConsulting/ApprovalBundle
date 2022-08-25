# Approval Bundle

The approval bundle can be used to setup an approval workflow for timesheets in Kimai.

## Users - Submit for Approval

The user can check their weeks and submit the timesheets week-by-week for approval. The user can see additionally the comments entered for the projects, can see the connected single time entries and can also see the history. 

The red information areas show possible issues with German law as it is for example forbidden to work 8h without a break.

The overview also displays the weekly hours a user should work together with the actual hours worked.

![Example process for Users](./_documentation/ApprovalUser.gif)

## Teamleads - Submit their timesheets and approve team members weeks

The Teamleads can see an overview of their own open weeks additionally to those of their team members open weeks and from the last week a complete overview. The teamlead can approve or deny the timesheets of their teamleads. Once an approval is accepted, the teamlead is not able to "undo" that acceptance.

A teamlead can also be a team member of a different team, then the teamleads week can be approved by that teamlead.

![Example process for Teamleads](./_documentation/ApprovalTeamlead.gif)

## Admins - Overview & Overrule

The admins can see and update any approval the same way the teamlead could do for their team. Additionally the admin is able to reset an already approved week.

![Example process for Admins](./_documentation/ApprovalAdmin.gif)

## Lockdown Process

The "LockdownPeriodPerUser" is used. As an admin you can see the lockdown settings per user. This period should only be updated by the approval bundle and not manually.

When a user sets a week to approval, then the end time of that week is used as lockdown date for this user. For this there cannot be any time entries added for this or any previous week.

When a week which was send to approval is denied (by teamlead or admin) or "undone" (by admin), then this week is opened, the lockdown date is set to the end of the previous week. As this would allow any following week to be updated as well, all weeks that follow that denied/re-opened week are also reset and need a new approval.