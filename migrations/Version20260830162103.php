<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830162103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__threat_source AS SELECT id, name, code, api_url, api_key, enabled, updated_at FROM threat_source');
        $this->addSql('DROP TABLE threat_source');
        $this->addSql('CREATE TABLE threat_source (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, api_url VARCHAR(1000) NOT NULL, api_key VARCHAR(500) DEFAULT NULL, enabled BOOLEAN NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO threat_source (id, name, code, api_url, api_key, enabled, updated_at) SELECT id, name, code, api_url, api_key, enabled, updated_at FROM __temp__threat_source');
        $this->addSql('DROP TABLE __temp__threat_source');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9230236377153098 ON threat_source (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__threat_source AS SELECT id, name, code, api_url, api_key, enabled, updated_at FROM threat_source');
        $this->addSql('DROP TABLE threat_source');
        $this->addSql('CREATE TABLE threat_source (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, api_url VARCHAR(1000) NOT NULL, api_key VARCHAR(500) DEFAULT NULL, enabled BOOLEAN NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO threat_source (id, name, code, api_url, api_key, enabled, updated_at) SELECT id, name, code, api_url, api_key, enabled, updated_at FROM __temp__threat_source');
        $this->addSql('DROP TABLE __temp__threat_source');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_THREAT_SOURCE_CODE ON threat_source (code)');
    }
}
