<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Enumeration;

abstract class FormEnum
{
    public const SUBMIT = 'submit';

    public const MONDAY = 'monday';
    public const TUESDAY = 'tuesday';
    public const WEDNESDAY = 'wednesday';
    public const THURSDAY = 'thursday';
    public const FRIDAY = 'friday';
    public const SATURDAY = 'saturday';
    public const SUNDAY = 'sunday';
    public const CUSTOMER_FOR_FREE_DAYS = 'customer_for_free_days';
    public const EMAIL_LINK_URL = 'email_link_url';
    public const WORKFLOW_START = 'workflow_start';
    public const OVERTIME_NY = 'approval_overtime_ny';
    public const BREAKCHECKS_NY = 'approval_breakchecks_ny';
    public const INCLUDE_ADMIN_NY = 'approval_include_admin_ny';
    public const TEAMLEAD_SELF_APPROVE_NY = 'approval_teamlead_selfapprove_ny';
    public const MAIL_SUBMITTED_NY = 'approval_mail_submitted_ny';
    public const MAIL_ACTION_NY = 'approval_mail_action_ny';
}
