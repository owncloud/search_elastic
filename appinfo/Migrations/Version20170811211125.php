<?php

namespace OCA\Search_Elastic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

/** Creates initial schema */
class Version20170811211125 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if (!$schema->hasTable("{$prefix}search_elastic_status")) {
			$table = $schema->createTable("{$prefix}search_elastic_status");
			$table->addColumn('fileid', 'bigint', [
				'notnull' => true,
				'default' => 0,
				'length' => 11,
			]);

			$table->addColumn('status', 'string', [
				'notnull' => false,
				'length' => 1,
				'default' => null,
			]);
			
			$table->addColumn('message', 'string', [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
			
			$table->setPrimaryKey(['fileid']);
			$table->addIndex(
				['status'],
				'es_status_index'
			);
		}
	}
}
