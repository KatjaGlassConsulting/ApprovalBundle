<?php

declare(strict_types=1);

namespace KimaiPlugin\ApprovalBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220318122512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE `kimai2_ext_approval_status` SET `name` = \'' . ApprovalStatus::SUBMITTED . '\' WHERE (`name` = \'' . ApprovalStatus::APPROVED . '\');');
        $this->addSql('UPDATE `kimai2_ext_approval_status` SET `name` = \'' . ApprovalStatus::APPROVED . '\' WHERE (`name` = \'' . ApprovalStatus::GRANTED . '\');');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
