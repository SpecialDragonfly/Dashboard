<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDeletedToPortfolio extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('portfolio');
        $table->addColumn('deleted', 'boolean', ['default' => 0, 'null' => false])->save();
    }
}
