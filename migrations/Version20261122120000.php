<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RGPD : date d\'acceptation de la politique de confidentialité';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `app_user` ADD privacy_accepted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `app_user` DROP privacy_accepted_at');
    }
}
