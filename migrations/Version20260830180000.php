<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user and threat_source tables for the admin dashboard.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE IF NOT EXISTS threat_source (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, api_url VARCHAR(1000) NOT NULL, api_key VARCHAR(500) DEFAULT NULL, enabled BOOLEAN NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_THREAT_SOURCE_CODE ON threat_source (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "user"');
        $this->addSql('DROP TABLE IF EXISTS threat_source');
    }
}
