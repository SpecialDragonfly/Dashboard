<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// Rebuild-only table — no equivalent in the pre-rebuild app. Backs the Charts
// feature's portfolio growth chart (see charts.md).
final class PortfolioValueHistory extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('portfolio_value_history');
        $table->addColumn('date', 'string')
              ->addColumn('symbol', 'string')
              ->addColumn('quantity', 'float')
              ->addColumn('market_price', 'float') // live Yahoo price at snapshot time — NOT portfolio.price (cost basis)
              ->addColumn('value', 'float')         // quantity * market_price
              ->addIndex(['date', 'symbol'], ['unique' => true])
              ->create();
    }
}
