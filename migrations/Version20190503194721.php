<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190503194721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add apitoken field.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ship CHANGE owner_id owner_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE fleet_id fleet_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE raw_data raw_data JSON NOT NULL');
        $this->addSql('ALTER TABLE citizen CHANGE organisations organisations JSON NOT NULL');
        $this->addSql('ALTER TABLE fleet CHANGE owner_id owner_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user ADD api_token CHAR(64) NOT NULL, CHANGE citizen_id citizen_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE token token CHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE citizen CHANGE organisations organisations LONGTEXT NOT NULL COLLATE utf8mb4_bin');
        $this->addSql('ALTER TABLE fleet CHANGE owner_id owner_id CHAR(36) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE ship CHANGE owner_id owner_id CHAR(36) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:uuid)\', CHANGE fleet_id fleet_id CHAR(36) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:uuid)\', CHANGE raw_data raw_data LONGTEXT NOT NULL COLLATE utf8mb4_bin');
        $this->addSql('ALTER TABLE user DROP api_token, CHANGE citizen_id citizen_id CHAR(36) DEFAULT \'NULL\' COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:uuid)\', CHANGE token token VARCHAR(64) NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
