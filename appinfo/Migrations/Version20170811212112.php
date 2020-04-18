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
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/** Updates some fields to bigint if required */
class Version20170811212112 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];

		if ($schema->hasTable("${prefix}search_elastic_status")) {
			$table = $schema->getTable("{$prefix}search_elastic_status");

			$fileIdColumn = $table->getColumn('fileid');
			if ($fileIdColumn && $fileIdColumn->getType()->getName() !== Types::BIGINT) {
				$fileIdColumn->setType(Type::getType(Types::BIGINT));
				$fileIdColumn->setOptions(['length' => 20]);
			}
		}
	}
}
