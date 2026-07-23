<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DividendPayments extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('dividend_payments');
        $table->addColumn('symbol_id', 'integer', ['signed' => false])
              ->addColumn('date', 'string')
              ->addColumn('amount', 'integer')
              ->addForeignKey('symbol_id', 'portfolio', 'id')
              ->create();
    }
}
