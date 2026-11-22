<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organisations : organisation, membres, invitations ; listes et questions rattachables à une organisation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, user_id INT NOT NULL, organization_id INT NOT NULL, invited_by_id INT DEFAULT NULL, UNIQUE INDEX uniq_membership_user_organization (user_id, organization_id), INDEX IDX_86FFD285A76ED395 (user_id), INDEX IDX_86FFD28532C8A3DE (organization_id), INDEX IDX_86FFD285A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, organization_id INT NOT NULL, invited_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F11D61A2B3BC57DA (token_hash), INDEX IDX_F11D61A232C8A3DE (organization_id), INDEX IDX_F11D61A2A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES `app_user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `app_user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `app_user` (id) ON DELETE SET NULL');

        // Dernier contexte choisi dans le sélecteur global.
        $this->addSql('ALTER TABLE `app_user` ADD last_organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `app_user` ADD CONSTRAINT FK_88BDF3E9D873E3EE FOREIGN KEY (last_organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88BDF3E9D873E3EE ON `app_user` (last_organization_id)');

        // Listes : rattachement optionnel à une organisation ; l'auteur devient facultatif (compte supprimé).
        $this->addSql('ALTER TABLE contact_list DROP FOREIGN KEY FK_6C377AE77E3C61F9');
        $this->addSql('ALTER TABLE contact_list ADD organization_id INT DEFAULT NULL, CHANGE owner_id owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_list ADD CONSTRAINT FK_6C377AE77E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contact_list ADD CONSTRAINT FK_6C377AE732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_6C377AE732C8A3DE ON contact_list (organization_id)');

        // Questions : idem.
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E7E3C61F9');
        $this->addSql('ALTER TABLE question ADD organization_id INT DEFAULT NULL, CHANGE owner_id owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_B6F7494E32C8A3DE ON question (organization_id)');
    }

    public function down(Schema $schema): void
    {
        // Les contenus d'organisation (ou sans auteur) ne peuvent pas redevenir personnels, et leurs réponses,
        // choix et contacts ne sont pas supprimés en cascade par la base : on refuse plutôt que de perdre des données.
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT (SELECT COUNT(*) FROM question WHERE organization_id IS NOT NULL OR owner_id IS NULL) + (SELECT COUNT(*) FROM contact_list WHERE organization_id IS NOT NULL OR owner_id IS NULL)') > 0,
            'Des listes ou questions appartiennent à une organisation ou n\'ont plus d\'auteur : supprimez-les avant de revenir en arrière.',
        );

        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E32C8A3DE');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E7E3C61F9');
        $this->addSql('DROP INDEX IDX_B6F7494E32C8A3DE ON question');
        $this->addSql('ALTER TABLE question DROP organization_id, CHANGE owner_id owner_id INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id)');

        $this->addSql('ALTER TABLE contact_list DROP FOREIGN KEY FK_6C377AE732C8A3DE');
        $this->addSql('ALTER TABLE contact_list DROP FOREIGN KEY FK_6C377AE77E3C61F9');
        $this->addSql('DROP INDEX IDX_6C377AE732C8A3DE ON contact_list');
        $this->addSql('ALTER TABLE contact_list DROP organization_id, CHANGE owner_id owner_id INT NOT NULL');
        $this->addSql('ALTER TABLE contact_list ADD CONSTRAINT FK_6C377AE77E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE `app_user` DROP FOREIGN KEY FK_88BDF3E9D873E3EE');
        $this->addSql('DROP INDEX IDX_88BDF3E9D873E3EE ON `app_user`');
        $this->addSql('ALTER TABLE `app_user` DROP last_organization_id');

        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE organization');
    }
}
