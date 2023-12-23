<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Extension;

use KimaiPlugin\ApprovalBundle\Toolbox\FormattingTool;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormattingExtension extends AbstractExtension
{
    public function __construct(private FormattingTool $formattingTool)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('formatDate', [$this, 'formatDate']),
            new TwigFilter('formatMonth', [$this, 'formatMonth']),
        ];
    }

    public function formatDate($date): string
    {
        return $this->formattingTool->formattingDate($date);
    }

    public function formatMonth($date): string
    {
        return $this->formattingTool->formattingYearMonth($date);
    }
}
