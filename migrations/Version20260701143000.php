<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add human scoring and bot filtering metadata to visitor tracking.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site_visitor')) {
            return;
        }

        $table = $schema->getTable('site_visitor');

        if (!$table->hasColumn('hit_count')) {
            $this->addSql('ALTER TABLE site_visitor ADD hit_count INT DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('human_score')) {
            $this->addSql('ALTER TABLE site_visitor ADD human_score INT DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('suspected_bot')) {
            $this->addSql('ALTER TABLE site_visitor ADD suspected_bot TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$table->hasColumn('bot_reason')) {
            $this->addSql('ALTER TABLE site_visitor ADD bot_reason VARCHAR(180) DEFAULT NULL');
        }

        if (!$table->hasIndex('IDX_SITE_VISITOR_HUMAN_SCORE')) {
            $this->addSql('CREATE INDEX IDX_SITE_VISITOR_HUMAN_SCORE ON site_visitor (human_score)');
        }

        if (!$table->hasIndex('IDX_SITE_VISITOR_SUSPECTED_BOT')) {
            $this->addSql('CREATE INDEX IDX_SITE_VISITOR_SUSPECTED_BOT ON site_visitor (suspected_bot)');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('site_visitor')) {
            return;
        }

        $table = $schema->getTable('site_visitor');

        if ($table->hasIndex('IDX_SITE_VISITOR_SUSPECTED_BOT')) {
            $this->addSql('DROP INDEX IDX_SITE_VISITOR_SUSPECTED_BOT ON site_visitor');
        }

        if ($table->hasIndex('IDX_SITE_VISITOR_HUMAN_SCORE')) {
            $this->addSql('DROP INDEX IDX_SITE_VISITOR_HUMAN_SCORE ON site_visitor');
        }

        if ($table->hasColumn('bot_reason')) {
            $this->addSql('ALTER TABLE site_visitor DROP bot_reason');
        }

        if ($table->hasColumn('suspected_bot')) {
            $this->addSql('ALTER TABLE site_visitor DROP suspected_bot');
        }

        if ($table->hasColumn('human_score')) {
            $this->addSql('ALTER TABLE site_visitor DROP human_score');
        }

        if ($table->hasColumn('hit_count')) {
            $this->addSql('ALTER TABLE site_visitor DROP hit_count');
        }
    }
}
