<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

// Rebuild-only table — no equivalent in the pre-rebuild app.
final class BlogPosts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('blog_posts');
        $table->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('title', 'string')
              ->addColumn('slug', 'string')
              ->addColumn('content', 'text')
              ->addColumn('tags', 'string', ['null' => true])
              ->addColumn('published', 'boolean', ['default' => 1])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['slug'], ['unique' => true])
              ->addForeignKey('user_id', 'users', 'id')
              ->create();
    }
}
