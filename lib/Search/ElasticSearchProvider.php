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

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\QueryString;
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\Node;
use OCP\IGroup;
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
	 * @var IGroup[]
	 */
	private $groups;

	/**
	 * @var SearchElasticConfigService
	 */
	private $config;

	/**
	 * @var SearchElasticService
	 */
	private $searchElasticService;

	/**
	 *
	 */
	private function setup() {
		$app = new Application();
		$container = $app->getContainer();

		$this->logger = $container->query(ILogger::class);
		$this->searchElasticService = $container->query(SearchElasticService::class);
		$this->user = $container->getServer()->getUserSession()->getUser();
		$this->groups = $container->getServer()->getGroupManager()->getUserGroups($this->user);
		$this->config = $container->query(SearchElasticConfigService::class);
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

		$results = [];
		try {
			$home = \OC::$server->getUserFolder($this->user->getUID());

			do {
				$resultSet = $this->fetchResults($query, $size, $page);
				foreach ($resultSet as $result) {
					$fileId = (int)$result->getId();
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
							$results[] = new ElasticSearchResult($result, $node, $home);
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

	/**
	 * @param string $query
	 * @param int $size
	 * @param int $page
	 * @return \Elastica\ResultSet
	 */
	public function fetchResults($query, $size, $page) {
		$es_filter = new BoolQuery();
		$es_filter->addShould(new MatchQuery('users', $this->user->getUID()));
		$noContentGroups = $this->config->getGroupNoContentArray();
		$searchContent = true;
		if (!$this->config->shouldContentBeIncluded()) {
			$searchContent = false;
		}
		foreach ($this->groups as $group) {
			$groupId = $group->getGID();
			$es_filter->addShould(new MatchQuery('groups', $groupId));
			if (\in_array($groupId, $noContentGroups)) {
				$searchContent = false;
			}
		}

		$es_bool = new BoolQuery();
		$es_bool->addFilter($es_filter);
		if ($searchContent) {
			$es_content_query = new QueryString($this->formatContentQuery($query));
			$es_content_query->setFields(["file.content"]);
			$es_content_query->setParam("analyze_wildcard", true);
			$es_bool->addShould($es_content_query);
		}

		$es_metadata_query = new QueryString($query . "*");
		$es_metadata_query->setFields(["name"]);
		$es_bool->addShould($es_metadata_query);
		$es_bool->setMinimumShouldMatch(1);

		$es_query = new Query($es_bool);
		$es_query->setHighlight([
			'fields' => [
				'file.content' => new \stdClass
			],
		]);

		// only the "mtime" field is being used at the moment
		$es_query->setSource(['includes' => ['mtime']]);
		$es_query->setSize($size);
		$es_query->setFrom(($page - 1) * $size);
		return $this->searchElasticService->search($es_query);
	}

	/**
	 * @param string $query
	 *
	 * @return string
	 */
	public function formatContentQuery($query) {
		$querySegments = \explode(" ", $query);
		$formattedQuery = "";
		// only add wildcards if no search syntax given
		if (!\preg_match('/\+|-|\*|\?|\||Ñ|\(|\)|\"/u', $query)) {
			foreach ($querySegments as $segment) {
				$formattedQuery.= "$segment* ";
			}
			return \trim($formattedQuery, " ");
		}
		return $query;
	}
}
