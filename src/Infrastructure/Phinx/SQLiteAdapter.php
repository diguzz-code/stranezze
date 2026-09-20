<?php
declare(strict_types=1);

namespace Stranezze\Infrastructure\Phinx;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\SQLiteAdapter as PhinxSQLiteAdapter;
use Stranezze\Infrastructure\DatabaseFactory;

final class SQLiteAdapter extends PhinxSQLiteAdapter
{
    public function setOptions(array $options): AdapterInterface
    {
        parent::setOptions($options);
        $this->suffix = '';

        return $this;
    }

    public function connect(): void
    {
        parent::connect();
        DatabaseFactory::configure($this->getConnection());
    }
}
