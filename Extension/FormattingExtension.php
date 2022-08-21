<?php

namespace KimaiPlugin\ApprovalBundle\Extension;

use KimaiPlugin\ApprovalBundle\Toolbox\FormattingTool;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormattingExtension extends AbstractExtension
{
    /**
     * @var FormattingTool
     */
    private $formattingTool;

    /**
     * @param FormattingTool $formattingTool
     */
    public function __construct(FormattingTool $formattingTool)
    {
        $this->formattingTool = $formattingTool;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('formatHours', [$this, 'formatHours']),
            new TwigFilter('formatDate', [$this, 'formatDate']),
            new TwigFilter('formatMonth', [$this, 'formatMonth']),
        ];
    }

    public function formatHours($duration): string
    {
        return $this->formattingTool->formattingDurationToHours($duration) . ' h';
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
