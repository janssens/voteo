<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121223852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Option « réponses anonymes » sur les questions';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE question ADD anonymous TINYINT(1) DEFAULT 0 NOT NULL');
        } else {
            $this->addSql('ALTER TABLE question ADD anonymous BOOLEAN DEFAULT false NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE question DROP anonymous');
    }
}
