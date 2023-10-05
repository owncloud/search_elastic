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
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\Connectors\ElasticaFactory;
use OCP\Files\Node;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

class ConnectorLegacy extends BaseConnector {
	public function __construct(
		Client $client,
		ElasticaFactory $factory,
		SearchElasticConfigService $esConfig,
		IGroupManager $groupManager,
		IUserManager $userManager,
		ILogger $logger
	) {
		parent::__construct($client, $factory, $esConfig, $groupManager, $userManager, $logger);
	}

	/**
	 * This is copied from the BaseConnector, mainly to ensure this is kept
	 * in case the default provided by the BaseConnector changes.
	 */
	protected function extractNodeData(Node $node, array $access): array {
		return [
			'name' => $node->getName(),
			'size' => $node->getSize(),
			'mtime' => $node->getMTime(),
			'users' => $access['users'],
			'groups' => $access['groups'],
		];
	}

	protected function getElasticSearchQuery(string $query, array $opts): array {
		$users = $opts['access']['users'] ?? [];
		$groups = $opts['access']['groups'] ?? [];
		$size = $opts['size'] ?? 30;
		$from = $opts['from'] ?? 0;

		$es_query = [
			'query' => [
				'bool' =>  [
					'filter' => [
						[
							'bool' => [
								'should' => [
									// assume there will be matches to be included here
									// coming from users and groups
								],
							],
						],
					],
					'should' => [
						[
							'query_string' => [
								'query' =>  "{$query}*",
								'fields' => ['name'],
							],
						],
					],
					'minimum_should_match' => 1,
				],
			],
			'highlight' => [
				'fields' => ['file.content' => new \stdClass]
			],
			'_source' => [
				'includes' => ['mtime']
			],
			'size' => $size,
			'from' => $from,
		];

		foreach ($users as $user) {
			$es_query['query']['bool']['filter'][0]['bool']['should'][] = ['match' => ['users' => $user]];
		}
		foreach ($groups as $group) {
			$es_query['query']['bool']['filter'][0]['bool']['should'][] = ['match' => ['groups' => $group]];
		}

		if ($opts['searchContent']) {
			$es_query['query']['bool']['should'][] = [
				'query_string' => [
					'query' => $this->formatContentQuery($query),
					'fields' => ['file.content'],
					'analyze_wildcard' => true,
				],
			];
		}
		return $es_query;
	}

	public function getConnectorName(): string {
		return 'Legacy';
	}

	protected function getPrivateConnectorName(): string {
		return '';
	}

	private function formatContentQuery($query) {
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
