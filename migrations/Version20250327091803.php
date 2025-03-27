<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250327091803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answer_choice (answer_id INT NOT NULL, choice_id INT NOT NULL, PRIMARY KEY(answer_id, choice_id))');
        $this->addSql('CREATE INDEX IDX_33526035AA334807 ON answer_choice (answer_id)');
        $this->addSql('CREATE INDEX IDX_33526035998666D1 ON answer_choice (choice_id)');
        $this->addSql('ALTER TABLE answer_choice ADD CONSTRAINT FK_33526035AA334807 FOREIGN KEY (answer_id) REFERENCES answer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE answer_choice ADD CONSTRAINT FK_33526035998666D1 FOREIGN KEY (choice_id) REFERENCES choice (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE answer DROP CONSTRAINT fk_dadd4a25998666d1');
        $this->addSql('DROP INDEX idx_dadd4a25998666d1');
        $this->addSql('ALTER TABLE answer DROP choice_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer_choice DROP CONSTRAINT FK_33526035AA334807');
        $this->addSql('ALTER TABLE answer_choice DROP CONSTRAINT FK_33526035998666D1');
        $this->addSql('DROP TABLE answer_choice');
        $this->addSql('ALTER TABLE answer ADD choice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT fk_dadd4a25998666d1 FOREIGN KEY (choice_id) REFERENCES choice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_dadd4a25998666d1 ON answer (choice_id)');
    }
}
