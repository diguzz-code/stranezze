<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE observations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL CHECK (length(title) BETWEEN 1 AND 80),
    content TEXT NOT NULL CHECK (length(content) BETWEEN 1 AND 500),
    category TEXT NOT NULL CHECK (category IN ('quotidiana', 'natura', 'persone', 'tecnologia', 'altro')),
    observed_on TEXT NOT NULL,
    place TEXT NOT NULL DEFAULT '' CHECK (length(place) <= 80),
    is_favorite INTEGER NOT NULL DEFAULT 0 CHECK (is_favorite IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $this->execute(
            'CREATE INDEX idx_observations_observed_on ON observations(observed_on DESC)'
        );
        $this->execute(
            'CREATE INDEX idx_observations_category ON observations(category)'
        );
    }

    public function down(): void
    {
        $this->execute('DROP INDEX idx_observations_observed_on');
        $this->execute('DROP INDEX idx_observations_category');
        $this->execute('DROP TABLE observations');
    }
}
