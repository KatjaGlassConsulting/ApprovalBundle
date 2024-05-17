<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Enumeration;

abstract class ConfigEnum
{
    public const USER = 'approval.user';
    public const META_FIELD_EMAIL_LINK_URL = 'approval.meta_field_email_link_url';
    public const CUSTOMER_FOR_FREE_DAYS = 'approval.customer_for_free_days';
    public const APPROVAL_WORKFLOW_START = 'approval.workflow_start';
    public const APPROVAL_OVERTIME_NY = 'approval.overtime_ny';
    public const APPROVAL_BREAKCHECKS_NY = 'approval.breakchecks_ny';
    public const APPROVAL_INCLUDE_ADMIN_NY = 'approval.include_admin_ny';
    public const APPROVAL_TEAMLEAD_SELF_APPROVE_NY = 'approval.teamlead_selfapprove_ny';
    public const APPROVAL_MAIL_SUBMITTED_NY = 'approval.mail_submitted_ny';
    public const APPROVAL_MAIL_ACTION_NY = 'approval.mail_action_ny';
}
