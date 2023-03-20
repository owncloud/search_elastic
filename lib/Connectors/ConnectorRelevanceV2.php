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
use Elastica\Result;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\Files\Node;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\ILogger;

class ConnectorRelevanceV2 extends BaseConnector {
	public function __construct(
		Client $client,
		SearchElasticConfigService $esConfig,
		IGroupManager $groupManager,
		IConfig $config,
		ILogger $logger
	) {
		parent::__construct($client, $esConfig, $groupManager, $config, $logger);
	}

	protected function getIndexSettingsConf(): array {
		return [
			'number_of_shards' => 1,
			'number_of_replicas' => 0,
			'analysis' => [
				'analyzer' => [
					'filename_analyzer' => [
						'type' => 'custom',
						'tokenizer' => 'filename_tokenizer',
						'filter' => ['lowercase'],
					],
					'filename_analyzer_ngram' => [
						'type' => 'custom',
						'tokenizer' => 'filename_tokenizer',
						'filter' => ['lowercase', 'filename_ngram'],
					],
					'filename_analyzer_edgegram' => [
						'type' => 'custom',
						'tokenizer' => 'filename_tokenizer',
						'filter' => ['lowercase', 'filename_edge_ngram'],
					],
				],
				'tokenizer' => [
					'filename_tokenizer' => [
						'type' => 'char_group',
						'tokenize_on_chars' => ['whitespace', 'punctuation'],
					],
				],
				'filter' => [
					'filename_ngram' => [
						'type' => 'ngram',
						'min_gram' => 2,
						'max_gram' => 3,
						'preserve_original' => true,
					],
					'filename_edge_ngram' => [
						'type' => 'edge_ngram',
						'min_gram' => 2,
						'max_gram' => 3,
						'preserve_original' => true,
					],
				],
			],
		];
	}

	protected function getMappingPropertiesConf(): array {
		return [
			'mtime' => [
				'type' => 'date',
				'format' => 'epoch_second',
			],
			'size' => ['type' => 'long'],
			'name' => [
				'type' => 'text',
				'analyzer' => 'filename_analyzer',
				'fields' => [
					'ngram' => [
						'type' => 'text',
						'analyzer' => 'filename_analyzer_ngram',
					],
					'edge_ngram' => [
						'type' => 'text',
						'analyzer' => 'filename_analyzer_edgegram',
					],
				],
			],
			'extension' => [
				'type' => 'text',
				'analyzer' => 'filename_analyzer',
			],
			'users' => ['type' => 'keyword'],
			'groups' => ['type' => 'keyword'],
		];
	}

	// processor configuration will be same as the base connector

	protected function extractNodeData(Node $node, array $access): array {
		$filename = $node->getName();
		$specialExts = ['.tar.gz', '.tar.bz2'];
		foreach ($specialExts as $specialExt) {
			$pos = \strripos($filename, $specialExt);
			if ($pos !== false) {
				$name = \substr($filename, 0, $pos);
				$extension = \substr($filename, $pos + 1);
				break;
			}
		}
		if (!isset($name, $extension)) {
			$pos = \strrpos($filename, '.');
			if ($pos === false || $pos === 0) {
				// no extension or it starts with "."
				$name = $filename;
				$extension = '';
			} else {
				$name = \substr($filename, 0, $pos);
				$extension = \substr($filename, $pos + 1);
			}
		}

		return [
			'name' => $name,
			'extension' => $extension,
			'size' => $node->getSize(),
			'mtime' => $node->getMTime(),
			'users' => $access['users'],
			'groups' => $access['groups'],
		];
	}

	protected function getElasticSearchQuery(string $query, array $opts): array {
		$users = \implode(' OR ', $opts['access']['users']);
		$groups = \implode(' OR ', $opts['access']['groups']);
		$size = $opts['size'] ?? 30;
		$from = $opts['from'] ?? 0;

		$es_query = [
			'query' => [
				'function_score' => [
					'functions' => [
						[
							'filter' => [
								'range' => [
									'mtime' => [
										'lt' => 'now-1y',
									],
								],
							],
							'weight' => 0.25,
						],
						[
							'filter' => [
								'range' => [
									'mtime' => [
										'lt' => 'now-1M',
									],
								],
							],
							'weight' => 0.5,
						],
						[
							'filter' => [
								'range' => [
									'mtime' => [
										'lt' => 'now-1w',
									],
								],
							],
							'weight' => 1,
						],
						[
							'filter' => [
								'range' => [
									'mtime' => [
										'lt' => 'now-1d',
									],
								],
							],
							'weight' => 2,
						],
						[
							'filter' => [
								'range' => [
									'mtime' => [
										'gte' => 'now-1d',
									],
								],
							],
							'weight' => 4,
						],
					],
					'score_mode' => 'first',
					'query' => [
						'bool' =>  [
							'filter' => [
								[
									'bool' => [
										'should' => [
											[
												'match' => [
													'users' => $users,
												]
											],
											[
												'match' => [
													'groups' => $groups,
												],
											],
										],
									],
								],
							],
							'should' => [
								[
									'query_string' => [
										'query' =>  $query,
										'fields' => ['name', 'name.edge_ngram^0.5', 'name.ngram^0.25'],
										'auto_generate_synonyms_phrase_query' => false,
										'type' => 'most_fields',
									],
								],
							],
							'minimum_should_match' => 1,
						],
					],
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

		if ($opts['searchContent']) {
			$es_query['query']['function_score']['query']['bool']['should'][] = [
				'query_string' => [
					'query' => $query,
					'fields' => ['file.content'],
				],
			];
		}
		return $es_query;
	}

	public function findInResult(Result $result, string $key) {
		switch ($key) {
			case 'id':
				return (int)$result->getId();
			case 'highlights':
				$highlights = $result->getHighlights();
				return $highlights['file.content'] ?? [];
			default:
				$data = $result->getData();
				return $data[$key] ?? null;
		}
	}

	public function getConnectorName(): string {
		return 'RelevanceV2';
	}

	protected function getPrivateConnectorName(): string {
		return '-relv2';
	}
}
