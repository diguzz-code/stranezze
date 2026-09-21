<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLoginAttempts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL CHECK (length(ip) BETWEEN 1 AND 45),
    username TEXT NOT NULL CHECK (length(username) BETWEEN 1 AND 80),
    attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success INTEGER NOT NULL CHECK (success IN (0, 1))
)
SQL);
        $this->execute('CREATE INDEX idx_login_attempts_ip_attempted_at ON login_attempts (ip, attempted_at)');
        $this->execute('CREATE INDEX idx_login_attempts_username_attempted_at ON login_attempts (username, attempted_at)');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE login_attempts');
    }
}