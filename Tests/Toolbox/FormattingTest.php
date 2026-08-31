<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Toolbox;

use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \KimaiPlugin\ApprovalBundle\Toolbox\Formatting
 */
class FormattingTest extends TestCase
{
    private function createSut(): Formatting
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id): string => match ($id) {
                'agendaWeek' => 'Week',
                default => $id,
            }
        );

        return new Formatting($translator);
    }

    /**
     * @dataProvider getDurationTestData
     */
    public function testFormatDuration(int $seconds, string $expected): void
    {
        self::assertEquals($expected, $this->createSut()->formatDuration($seconds));
    }

    public static function getDurationTestData(): \Generator
    {
        yield 'zero' => [0, '0:00'];
        yield 'below ten minutes' => [480, '0:08'];
        yield 'below one hour' => [3540, '0:59'];
        yield 'exactly one hour' => [3600, '1:00'];
        yield 'one hour and one minute' => [3660, '1:01'];
        yield 'nine and a half hours' => [34200, '9:30'];
        yield 'negative' => [-3600, '-1:00'];
        yield 'negative with minutes' => [-34200, '-9:30'];
    }

    public function testParseDateInsideTheWeek(): void
    {
        // 2024-08-28 is a Wednesday in ISO week 35
        self::assertEquals(
            'August 2024 - Week 35 [26.08.2024 - 01.09.2024]',
            $this->createSut()->parseDate(new \DateTime('2024-08-28'))
        );
    }

    public function testParseDateOnAMondayUsesThatDayAsWeekStart(): void
    {
        self::assertEquals(
            'August 2024 - Week 35 [26.08.2024 - 01.09.2024]',
            $this->createSut()->parseDate(new \DateTime('2024-08-26'))
        );
    }

    public function testParseDateDoesNotModifyTheGivenDate(): void
    {
        $date = new \DateTime('2024-08-28 13:45:00');
        $this->createSut()->parseDate($date);

        self::assertEquals('2024-08-28 13:45:00', $date->format('Y-m-d H:i:s'));
    }
}
