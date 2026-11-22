<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial MariaDB. Remplace les anciennes migrations PostgreSQL.
 */
final class Version20261121235857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial (MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE answer (
              id INT AUTO_INCREMENT NOT NULL,
              token VARCHAR(50) NOT NULL,
              contact_id INT NOT NULL,
              question_id INT NOT NULL,
              INDEX IDX_DADD4A25E7A1254A (contact_id),
              INDEX IDX_DADD4A251E27F6BF (question_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE answer_choice (
              answer_id INT NOT NULL,
              choice_id INT NOT NULL,
              INDEX IDX_33526035AA334807 (answer_id),
              INDEX IDX_33526035998666D1 (choice_id),
              PRIMARY KEY (answer_id, choice_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `app_user` (
              id INT AUTO_INCREMENT NOT NULL,
              firstname VARCHAR(255) NOT NULL,
              lastname VARCHAR(255) NOT NULL,
              email VARCHAR(255) NOT NULL,
              username VARCHAR(50) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              is_verified TINYINT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE choice (
              id INT AUTO_INCREMENT NOT NULL,
              label VARCHAR(255) NOT NULL,
              color VARCHAR(15) DEFAULT NULL,
              text_color VARCHAR(15) DEFAULT NULL,
              border_color VARCHAR(15) DEFAULT NULL,
              question_id INT NOT NULL,
              INDEX IDX_C1AB5A921E27F6BF (question_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact (
              id INT AUTO_INCREMENT NOT NULL,
              firstname VARCHAR(255) NOT NULL,
              lastname VARCHAR(255) NOT NULL,
              email VARCHAR(255) NOT NULL,
              list_id INT NOT NULL,
              INDEX IDX_4C62E6383DAE168B (list_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_list (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              owner_id INT NOT NULL,
              INDEX IDX_6C377AE77E3C61F9 (owner_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE question (
              id INT AUTO_INCREMENT NOT NULL,
              intro LONGTEXT DEFAULT NULL,
              outro LONGTEXT DEFAULT NULL,
              is_multichoices TINYINT NOT NULL,
              results_visible_before_vote TINYINT DEFAULT 0 NOT NULL,
              anonymous TINYINT DEFAULT 0 NOT NULL,
              closes_at DATETIME DEFAULT NULL,
              send_results_on_close TINYINT DEFAULT 0 NOT NULL,
              results_sent_at DATETIME DEFAULT NULL,
              subject VARCHAR(255) NOT NULL,
              sent TINYINT NOT NULL,
              owner_id INT NOT NULL,
              INDEX IDX_B6F7494E7E3C61F9 (owner_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE question_contact_list (
              question_id INT NOT NULL,
              contact_list_id INT NOT NULL,
              INDEX IDX_FFDD81A51E27F6BF (question_id),
              INDEX IDX_FFDD81A5A781370A (contact_list_id),
              PRIMARY KEY (question_id, contact_list_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              answer
            ADD
              CONSTRAINT FK_DADD4A25E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              answer
            ADD
              CONSTRAINT FK_DADD4A251E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              answer_choice
            ADD
              CONSTRAINT FK_33526035AA334807 FOREIGN KEY (answer_id) REFERENCES answer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              answer_choice
            ADD
              CONSTRAINT FK_33526035998666D1 FOREIGN KEY (choice_id) REFERENCES choice (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              choice
            ADD
              CONSTRAINT FK_C1AB5A921E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contact
            ADD
              CONSTRAINT FK_4C62E6383DAE168B FOREIGN KEY (list_id) REFERENCES contact_list (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              contact_list
            ADD
              CONSTRAINT FK_6C377AE77E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              question
            ADD
              CONSTRAINT FK_B6F7494E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `app_user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              question_contact_list
            ADD
              CONSTRAINT FK_FFDD81A51E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              question_contact_list
            ADD
              CONSTRAINT FK_FFDD81A5A781370A FOREIGN KEY (contact_list_id) REFERENCES contact_list (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A25E7A1254A');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A251E27F6BF');
        $this->addSql('ALTER TABLE answer_choice DROP FOREIGN KEY FK_33526035AA334807');
        $this->addSql('ALTER TABLE answer_choice DROP FOREIGN KEY FK_33526035998666D1');
        $this->addSql('ALTER TABLE choice DROP FOREIGN KEY FK_C1AB5A921E27F6BF');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E6383DAE168B');
        $this->addSql('ALTER TABLE contact_list DROP FOREIGN KEY FK_6C377AE77E3C61F9');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E7E3C61F9');
        $this->addSql('ALTER TABLE question_contact_list DROP FOREIGN KEY FK_FFDD81A51E27F6BF');
        $this->addSql('ALTER TABLE question_contact_list DROP FOREIGN KEY FK_FFDD81A5A781370A');
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE answer_choice');
        $this->addSql('DROP TABLE `app_user`');
        $this->addSql('DROP TABLE choice');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE contact_list');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE question_contact_list');
    }
}
