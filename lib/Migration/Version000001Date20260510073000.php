<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260510073000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('ncextrak_jobs')) {
            return $schema;
        }

        $table = $schema->createTable('ncextrak_jobs');
        $table->addColumn('id', 'integer', [
            'autoincrement' => true,
            'unsigned' => true,
            'notnull' => true,
        ]);
        $table->addColumn('uid', 'string', [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addColumn('source_file_id', 'integer', [
            'unsigned' => true,
            'notnull' => true,
        ]);
        $table->addColumn('state', 'string', [
            'length' => 16,
            'default' => 'queued',
            'notnull' => true,
        ]);
        $table->addColumn('progress', 'integer', [
            'unsigned' => true,
            'default' => 0,
            'notnull' => true,
        ]);
        $table->addColumn('error', 'text', [
            'notnull' => false,
            'length' => 65535,
        ]);
        $table->addColumn('target_folder', 'string', [
            'length' => 255,
            'default' => '',
            'notnull' => true,
        ]);
        $table->addColumn('overwrite', 'boolean', [
            'default' => false,
            'notnull' => true,
        ]);
        $table->addColumn('result_payload', 'text', [
            'notnull' => false,
            'length' => 65535,
        ]);
        $table->addColumn('created_at', 'integer', [
            'unsigned' => true,
            'notnull' => true,
        ]);
        $table->addColumn('updated_at', 'integer', [
            'unsigned' => true,
            'notnull' => true,
        ]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['uid'], 'ncextrak_jobs_uid_idx');
        $table->addIndex(['state'], 'ncextrak_jobs_state_idx');

        return $schema;
    }
}
