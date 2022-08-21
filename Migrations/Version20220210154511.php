<?php

declare(strict_types=1);

namespace KimaiPlugin\ApprovalBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
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
