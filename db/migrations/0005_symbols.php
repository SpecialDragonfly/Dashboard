<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Symbols extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('symbols');
        $table->addColumn('ticker', 'string')
              ->addColumn('last-checked', 'integer')
              ->addIndex('ticker', ['unique' => true])
              ->create();
    }
}
