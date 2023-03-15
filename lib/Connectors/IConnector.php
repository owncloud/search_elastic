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

use OCP\Files\Node;
use Elastica\Result;
use Elastica\ResultSet;

interface IConnector {
	/**
	 * Whether the index attached to this connector has been setup.
	 * If `isSetup` returns false, `prepareIndex` should be call to setup
	 * the index. If `isSetup` returns true, `prepareIndex` shouldn't be
	 * called.
	 * @return bool
	 */
	public function isSetup(): bool;

	/**
	 * Prepare / Initialize the index. This involves setting up the index,
	 * preparing the mappings if any, setting up the ingest pipeline, etc
	 */
	public function prepareIndex();

	/**
	 * Index the provide node (file).
	 * @param string $userId the user whose home folder is being indexed
	 * @param Node $node the node to be indexed
	 * @param bool $extractContent whether the content of the node should be
	 * extracted and indexed (content might be ignored)
	 * @return bool
	 */
	public function indexNode(string $userId, Node $node, bool $extractContent = true): bool;

	/**
	 * Send the query and fetch the results for the userId.
	 * @params string $userId the user whose results will be fetched for. Different
	 * users might get different results
	 * @param string $query the query to be sent as written by the user. It
	 * could be something like "noma", "nomad", "nom*", "nom AND content:desert",
	 * "all at once", etc
	 * @param int $limit the number of results to be returned
	 * @param int $offset the offset of the results
	 * @return array
	 */
	public function fetchResults(string $userId, string $query, int $limit, int $offset): ResultSet;

	/**
	 * Find the key in the result and return its value.
	 * This is needed since the required keys could be in different places
	 * inside the result, based on the connector, or could be mapped differently.
	 * For example, the modification time could be indexed as 'modTime', so
	 * the result will have that field, but ownCloud only knows about 'mtime'
	 */
	public function findInResult(Result $result, string $key);

	/**
	 * The delete the indexed document by the ownCloud's fileid.
	 * Usually, the fileid should be indexed as document id, but this
	 * might differ
	 */
	public function deleteByFileId($fileId): bool;

	/**
	 * Get the stats for the attached index, as reported by elasticsearch
	 */
	public function getStats(): array;

	/**
	 * Get the name of the connector, for identification.
	 * The connector's name will be used as a part of app configuration
	 * keys, so they need to be under 30 chars.
	 * @return string the name of the connector
	 */
	public function getConnectorName(): string;
}
