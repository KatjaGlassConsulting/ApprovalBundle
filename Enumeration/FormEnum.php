<?php

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
}
