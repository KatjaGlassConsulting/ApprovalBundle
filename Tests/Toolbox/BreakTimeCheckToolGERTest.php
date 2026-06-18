<?php

namespace KimaiPlugin\ApprovalBundle\Tests\Toolbox;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use DateTime;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BreakTimeCheckToolGERTest extends TestCase
{
    private MockObject|TranslatorInterface $translator;
    private MockObject|SettingsTool $settingsTool;
    private BreakTimeCheckToolGER $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnCallback(static fn(string $key) => $key);

        $this->settingsTool = $this->createMock(SettingsTool::class);
        $this->settingsTool->method('getConfiguration')->willReturn(null);

        $this->tool = new BreakTimeCheckToolGER($this->translator, $this->settingsTool);
    }

    public function testCheckBreakTimeDetailsKeepsDayErrorsButScopesTimesheetErrors(): void
    {
        $timesheetOne = $this->createTimesheet('2026-03-03 08:00:00', '2026-03-03 11:00:00', 3 * 60 * 60);
        $timesheetTwo = $this->createTimesheet('2026-03-03 11:15:00', '2026-03-03 14:30:00', (3 * 60 * 60) + (15 * 60));

        $details = $this->tool->checkBreakTimeDetails([$timesheetOne, $timesheetTwo]);

        $expectedErrors = [
            'error.six_hours_without_stop_break',
            'error.six_hours_without_break',
        ];

        $this->assertSame($expectedErrors, $details['days']['2026-03-03']);
        $this->assertSame([], $details['timesheets'][spl_object_id($timesheetOne)]);
        $this->assertSame($expectedErrors, $details['timesheets'][spl_object_id($timesheetTwo)]);
        $this->assertSame($details['days'], $this->tool->checkBreakTime([$timesheetOne, $timesheetTwo]));
    }

    private function createTimesheet(string $begin, string $end, int $duration): Timesheet
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(1);

        $project = $this->createMock(Project::class);
        $project->method('getCustomer')->willReturn($customer);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        $timesheet = $this->createMock(Timesheet::class);
        $timesheet->method('getBegin')->willReturn(new DateTime($begin));
        $timesheet->method('getEnd')->willReturn(new DateTime($end));
        $timesheet->method('getDuration')->willReturn($duration);
        $timesheet->method('getProject')->willReturn($project);
        $timesheet->method('getUser')->willReturn($user);

        return $timesheet;
    }
}