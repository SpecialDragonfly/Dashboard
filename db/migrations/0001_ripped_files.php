<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RippedFiles extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('ripped_files');
        $table->addColumn('video_id', 'string')  // the id from the url
              ->addColumn('url', 'string')        // the original url
              ->addColumn('created', 'datetime')
              // title/thumbnail/path are only known once the rip finishes —
              // RippedFile::isReady() depends on path being true NULL until then.
              ->addColumn('title', 'string', ['null' => true])
              ->addColumn('thumbnail', 'string', ['null' => true])
              ->addColumn('path', 'string', ['null' => true])
              ->addIndex('url')
              ->addIndex('video_id')
              ->create();
    }
}
