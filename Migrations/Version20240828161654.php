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
final class Version20240828161654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A8341CE3FE65F000');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A8341CE36BF700BD');

        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A9341CE3FE65F000 FOREIGN KEY (approval_id) REFERENCES kimai2_ext_approval (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A9341CE36BF700BD FOREIGN KEY (status_id) REFERENCES kimai2_ext_approval_status (id) ON DELETE CASCADE');
    }


    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A9341CE3FE65F000');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history DROP FOREIGN KEY FK_A9341CE36BF700BD');
    
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A8341CE3FE65F000 FOREIGN KEY (approval_id) REFERENCES kimai2_ext_approval (id)');
        $this->addSql('ALTER TABLE kimai2_ext_approval_history ADD CONSTRAINT FK_A8341CE36BF700BD FOREIGN KEY (status_id) REFERENCES kimai2_ext_approval_status (id)');
    }
}
