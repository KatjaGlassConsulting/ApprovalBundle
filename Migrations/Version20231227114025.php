<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231227114025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
              UPDATE kimai2_ext_approval as app
              INNER JOIN (
                SELECT ap.user_id, ap.start_date, ap.end_date, SUM(t.duration) AS dur_sum
                  FROM kimai2_ext_approval as ap, kimai2_timesheet AS t
                  WHERE ap.user_id = t.user AND t.start_time >= ap.start_date AND DATE_FORMAT(t.end_time,"%Y-%m-%d") <= ap.end_date
                  GROUP BY ap.user_id, ap.start_date, ap.end_date
              ) AS tm ON app.user_id = tm.user_id AND app.start_date = tm.start_date
              SET app.actual_duration = tm.dur_sum');
    }

    public function down(Schema $schema): void
    {
    }
}
