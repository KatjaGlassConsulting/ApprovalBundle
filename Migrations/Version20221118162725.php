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
final class Version20221118162725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kimai2_ext_approval_workday_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, monday INT NOT NULL, tuesday INT NOT NULL, wednesday INT NOT NULL, thursday INT NOT NULL, friday INT NOT NULL, saturday INT NOT NULL, sunday INT NOT NULL, valid_till DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_785CWEC0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kimai2_ext_approval_workday_history ADD CONSTRAINT FK_785CWEC0A76ED395 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kimai2_ext_approval_workday_history DROP FOREIGN KEY FK_785CWEC0A76ED395');
        $this->addSql('DROP TABLE kimai2_ext_approval_workday_history');
    }
}
