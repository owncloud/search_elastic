<?php
/**
 * @copyright Copyright (c) 2023, ownCloud GmbH
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

namespace OCA\Search_Elastic\Connectors;

use Elastica\Client;
use Elastica\Index;
use Elastica\Mapping;
use Elastica\Document;
use Elastica\Bulk;
use Elastica\Query;
use Elastica\Search;

/**
 * A class dedicated to create Elastica objects. This is intended
 * to help with the unit tests by injecting a mock of this class
 * instead of creating the objects directly
 */
class ElasticaFactory {
	public function getNewIndex(Client $client, string $name) {
		return new Index($client, $name);
	}

	public function getNewMapping() {
		return new Mapping();
	}

	public function getNewDocument(string $id) {
		return new Document($id);
	}

	public function getNewBulk(Client $client) {
		return new Bulk($client);
	}

	public function getNewQuery() {
		return new Query();
	}

	public function getNewSearch(Client $client) {
		return new Search($client);
	}
}
