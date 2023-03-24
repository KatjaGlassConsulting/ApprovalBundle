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

    public const META_FIELD_EXPECTED_WORKING_TIME_ON_MONDAY = 'approval.meta_field_expected_working_time_on_monday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_TUESDAY = 'approval.meta_field_expected_working_time_on_tuesday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_WEDNESDAY = 'approval.meta_field_expected_working_time_on_wednesday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_THURSDAY = 'approval.meta_field_expected_working_time_on_thursday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_FRIDAY = 'approval.meta_field_expected_working_time_on_friday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_SATURDAY = 'approval.meta_field_expected_working_time_on_saturday';
    public const META_FIELD_EXPECTED_WORKING_TIME_ON_SUNDAY = 'approval.meta_field_expected_working_time_on_sunday';
    public const META_FIELD_EMAIL_LINK_URL = 'approval.meta_field_email_link_url';
    public const CUSTOMER_FOR_FREE_DAYS = 'approval.customer_for_free_days';
    public const APPROVAL_WORKFLOW_START = 'approval.workflow_start';
    public const APPROVAL_OVERTIME_NY = 'approval.overtime_ny';
    public const APPROVAL_BREAKCHECKS_NY = 'approval.breakchecks_ny';
}
