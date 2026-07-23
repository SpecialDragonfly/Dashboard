<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PortfolioMigration extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('portfolio');
        $table->addColumn('symbol', 'string')
              ->addColumn('name', 'string')
              ->addColumn('quantity', 'float')
              ->addColumn('price', 'float')
              ->addColumn('ex-div', 'string', ['null' => true])
              ->addColumn('dividend', 'string', ['null' => true])
              ->create();
    }
}
