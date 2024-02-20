<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApprovalBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231016134127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kimai2_ext_approval_overtime_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, duration INT NOT NULL, apply_date DATE NOT NULL, INDEX IDX_785SHOC0A96ED141 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kimai2_ext_approval_overtime_history ADD CONSTRAINT FK_785SHOC0A96ED141 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
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
        $this->addSql('ALTER TABLE kimai2_ext_approval_overtime_history DROP FOREIGN KEY FK_785SHOC0A96ED141');
        $this->addSql('DROP TABLE kimai2_ext_approval_overtime_history');
    }
}
