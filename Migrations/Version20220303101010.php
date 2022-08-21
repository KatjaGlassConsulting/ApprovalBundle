<?php

declare(strict_types=1);

namespace KimaiPlugin\ApprovalBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220303101010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `kimai2_ext_approval_status` SET `name` = \'' . ApprovalStatus::APPROVED . '\' WHERE (`name` = \'submitted\');');
    }

    public function down(Schema $schema): void
    {
    }
}
