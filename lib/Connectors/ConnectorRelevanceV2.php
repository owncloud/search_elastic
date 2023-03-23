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
use OCP\Files\FileInfo;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

class ConnectorRelevanceV2 extends BaseConnector {
	public function __construct(
		Client $client,
		SearchElasticConfigService $esConfig,
		IGroupManager $groupManager,
		IUserManager $userManager,
		ILogger $logger
	) {
		parent::__construct($client, $esConfig, $groupManager, $userManager, $logger);
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
				'properties' => [
					'stamp' => ['type' => 'date', 'format' => 'epoch_second'],
					'date' => ['type' => 'date', 'format' => 'date'],
					'datetime' => ['type' => 'date', 'format' => 'date_time_no_millis'],
				],
			],
			'size' => [
				'properties' => [
					'b' => ['type' => 'long'],
					'mb' => ['type' => 'double'],
				],
			],
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
			'ext' => [
				'type' => 'text',
				'analyzer' => 'filename_analyzer',
			],
			'type' => ['type' => 'keyword'],
			'mime' => [
				'type' => 'text',
				'fields' => [
					'key' => ['type' => 'keyword'],
				],
			],
			'users' => ['type' => 'keyword'],
			'groups' => ['type' => 'keyword'],
		];
	}

	protected function getProcessorConf(): array {
		$processors = [
			[
				'attachment' => [
					'field' => 'data',
					'target_field' => 'file',
					'indexed_chars' => '-1',
				]
			],
			[
				'remove' => [
					'field' => 'data',
				]
			],
		];
		$description = "Pipeline to process entries for ownCloud search with connector {$this->getConnectorName()}";

		$payload = [];
		$payload['description'] = $description;
		$payload['processors'] = $processors;
		return $payload;
	}

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

		$size = $node->getSize();
		$time = $node->getMTime();
		$timeObj = new \DateTime();
		$timeObj->setTimestamp($time);

		$nodeType = $node->getType();
		$type = 'file';
		if ($nodeType === FileInfo::TYPE_FOLDER) {
			$type = 'folder';
		}

		return [
			'name' => $name,
			'ext' => $extension,
			'size' => [
				'b' => $size,
				'mb' => $size / (1024 * 1024),
			],
			'mtime' => [
				'stamp' => $time,
				'date' => $timeObj->format('Y-m-d'),
				'datetime' => $timeObj->format('c'),
			],
			'type' => $type,
			'mime' => $node->getMimetype(),
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
									'mtime.stamp' => [
										'lt' => 'now-1y',
									],
								],
							],
							'weight' => 0.25,
						],
						[
							'filter' => [
								'range' => [
									'mtime.stamp' => [
										'lt' => 'now-1M',
									],
								],
							],
							'weight' => 0.5,
						],
						[
							'filter' => [
								'range' => [
									'mtime.stamp' => [
										'lt' => 'now-1w',
									],
								],
							],
							'weight' => 1,
						],
						[
							'filter' => [
								'range' => [
									'mtime.stamp' => [
										'lt' => 'now-1d',
									],
								],
							],
							'weight' => 2,
						],
						[
							'filter' => [
								'range' => [
									'mtime.stamp' => [
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
				'includes' => ['mtime.stamp']
			],
			'size' => $size,
			'from' => $from,
		];

		if ($opts['searchContent']) {
			$es_query['query']['function_score']['query']['bool']['should'][0]['query_string']['fields'][] = 'file.content';
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
			case 'mtime':
				$data = $result->getData();
				return $data['mtime']['stamp'];
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
