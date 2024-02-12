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
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220210154511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO `kimai2_ext_approval_status` (`name`) VALUES (\'' . ApprovalStatus::GRANTED . '\');');
        $this->addSql('INSERT INTO `kimai2_ext_approval_status` (`name`) VALUES (\'' . ApprovalStatus::DENIED . '\');');
        $this->addSql('INSERT INTO `kimai2_ext_approval_status` (`name`) VALUES (\'' . ApprovalStatus::SUBMITTED . '\');');
    }

    public function down(Schema $schema): void
    {
    }
}
