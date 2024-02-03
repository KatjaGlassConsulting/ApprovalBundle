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
final class Version20220208134542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kimai2_ext_approval (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, expected_duration INT NOT NULL, creation_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_775C89B0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kimai2_ext_approval_history (id INT AUTO_INCREMENT NOT NULL, approval_id INT NOT NULL, user_id INT NOT NULL, status_id INT NOT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_A8341CE3FE65F000 (approval_id), INDEX IDX_A8341CE3A76ED395 (user_id), INDEX IDX_A8341CE36BF700BD (status_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kimai2_ext_approval_status (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kimai2_ext_approval ADD CONSTRAINT FK_775C89B0A76ED395 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A8341CE3FE65F000 FOREIGN KEY (approval_id) REFERENCES kimai2_ext_approval (id)');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A8341CE3A76ED395 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A8341CE36BF700BD FOREIGN KEY (status_id) REFERENCES kimai2_ext_approval_status (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A8341CE3FE65F000');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A8341CE36BF700BD');
        $this->addSql('DROP TABLE kimai2_ext_approval');
        $this->addSql('DROP TABLE kimai2_ext_approval_history');
        $this->addSql('DROP TABLE kimai2_ext_approval_status');
    }
}
