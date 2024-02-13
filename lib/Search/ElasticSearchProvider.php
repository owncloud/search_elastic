<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Sujith H <sharidasan@owncloud.com>
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

namespace OCA\Search_Elastic\Search;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Connectors\Hub;
use OCP\Files\Node;
use OCP\ILogger;
use OCP\IUser;
use OCP\Search\PagedProvider;

class ElasticSearchProvider extends PagedProvider {
	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var IUser
	 */
	private $user;

	/**
	 * @var Hub
	 */
	private $hub;

	/**
	 *
	 */
	private function setup() {
		$app = new Application();
		$container = $app->getContainer();

		$this->logger = $container->query(ILogger::class);
		$this->hub = $container->query(Hub::class);
		$this->user = $container->getServer()->getUserSession()->getUser();
	}

	/**
	 * Search for $query
	 * @param string $query
	 * @param int $page pages start at page 1
	 * @param int $size, 0 = all
	 * @return ElasticSearchResult[]
	 */
	public function searchPaged($query, $page, $size) {
		if (empty($query)) {
			return [];
		}

		$this->setup();

		$this->hub->prepareSearchIndex();
		$results = [];
		try {
			$home = \OC::$server->getUserFolder($this->user->getUID());

			do {
				$from = ($page - 1) * $size;
				$fetchResult = $this->hub->hubFetchResults($this->user->getUID(), $query, $size, $from);
				$resultSet = $fetchResult['resultSet'];
				$searchConnector = $fetchResult['connector'];
				foreach ($resultSet as $result) {
					$fileId = $searchConnector->findInResult($result, 'id');
					$nodes = $home->getById($fileId);

					if (empty($nodes[0])) {
						$this->logger->debug(
							"Could not find file for id $fileId in"
							. " storage {$home->getStorage()->getId()}'."
							. " Removing it from results. Maybe it was unshared"
							. " for {$this->user->getUID()}. A background job will"
							. " update the index with the new permissions.",
							['app' => 'search_elastic']
						);
					}

					foreach ($nodes as $node) {
						if ($node instanceof Node) {
							$results[] = new ElasticSearchResult($result, $searchConnector, $node, $home);
						} else {
							$this->logger->error(
								"Expected a Node for $fileId, received "
								. \json_encode($node),
								['app' => 'search_elastic']
							);
						}
					}
				}
				$page++;
				// TODO We try to compensate for removed entries, but this will confuse page counting of the webui
				// Maybe add fake entries?
			} while ($resultSet->getTotalHits() === $size && \count($results) < $size);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'search_elastic']);
		}
		return $results;
	}

	/**
	 * get the base type of an internet media type string
	 *
	 * returns 'text' for 'text/plain'
	 *
	 * @param string $mimeType internet media type
	 * @return string top-level type
	 */
	public static function baseTypeOf($mimeType) {
		return \substr($mimeType, 0, \strpos($mimeType, '/'));
	}
}
