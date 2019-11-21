<?php
/**
 * @author Victor Dubiniuk <victor.dubiniuk@gmail.com>
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */
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
