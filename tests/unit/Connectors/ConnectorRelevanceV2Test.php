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

namespace OCA\Search_Elastic\Tests\Unit\Connectors;

use Elastica\Client;
use Elastica\Index;
use Elastica\Mapping;
use Elastica\Request;
use Elastica\Response;
use Elastica\Query;
use Elastica\Search;
use Elastica\Result;
use Elastica\Index\Stats;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IGroup;
use OCP\ILogger;
use OCA\Search_Elastic\Connectors\ConnectorRelevanceV2;
use OCA\Search_Elastic\Connectors\ElasticaFactory;
use Test\TestCase;

class ConnectorRelevanceV2Test extends TestCase {
	/** @var Client */
	private $client;
	/** @var ElasticaFactory */
	private $factory;
	/** @var SearchElasticConfigService */
	private $esConfig;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ILogger */
	private $logger;

	/** @var ConnectorRelevanceV2 */
	private $connectorRelevanceV2;

	protected function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(Client::class);
		$this->factory = $this->createMock(ElasticaFactory::class);
		$this->esConfig = $this->createMock(SearchElasticConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->connectorRelevanceV2 = new ConnectorRelevanceV2(
			$this->client,
			$this->factory,
			$this->esConfig,
			$this->groupManager,
			$this->userManager,
			$this->logger
		);
	}

	public function testGetConnectorName() {
		$this->assertSame('RelevanceV2', $this->connectorRelevanceV2->getConnectorName());
	}

	public function testIsSetupMissingIndex() {
		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('exists')
			->willReturn(false);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		// no request to check the ingest pipeline is needed
		$this->client->expects($this->never())
			->method('request');

		$this->assertFalse($this->connectorRelevanceV2->isSetup());
	}

	public function testIsSetupWrongPipeline() {
		$this->esConfig->method('getRecommendedPrefixFor')
			->will($this->returnValueMap([
				['index', 'oc-instanceid'],
				['processor', 'oc-processor-instanceid'],
			]));

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('exists')
			->willReturn(true);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$response = $this->createMock(Response::class);
		$response->method('getStatus')->willReturn(404);

		$this->client->expects($this->once())
			->method('request')
			->with('_ingest/pipeline/oc-processor-instanceid-relv2', Request::GET)
			->willReturn($response);

		$this->assertFalse($this->connectorRelevanceV2->isSetup());
	}

	public function testIsSetup() {
		$this->esConfig->method('getRecommendedPrefixFor')
			->will($this->returnValueMap([
				['index', 'oc-instanceid'],
				['processor', 'oc-processor-instanceid'],
			]));

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('exists')
			->willReturn(true);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$response = $this->createMock(Response::class);
		$response->method('getStatus')->willReturn(200);

		$this->client->expects($this->once())
			->method('request')
			->with('_ingest/pipeline/oc-processor-instanceid-relv2', Request::GET)
			->willReturn($response);

		$this->assertTrue($this->connectorRelevanceV2->isSetup());
	}

	public function testPrepareIndex() {
		$this->esConfig->method('getRecommendedPrefixFor')
			->will($this->returnValueMap([
				['index', 'oc-instanceid'],
				['processor', 'oc-processor-instanceid'],
			]));

		$expectedIndexConf = [
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

		$expectedMappingConf = [
			'mtime' => [
				'type' => 'date',
				'format' => 'epoch_second||date||date_time_no_millis',
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

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('create')
			->with(['settings' => $expectedIndexConf], true);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$mappingMock = $this->createMock(Mapping::class);
		$mappingMock->expects($this->once())
			->method('setProperties')
			->with($expectedMappingConf);
		$mappingMock->expects($this->once())
			->method('send')
			->with($indexMock);
		$this->factory->expects($this->once())
			->method('getNewMapping')
			->willReturn($mappingMock);

		$expectedPayload = [
			'description' =>  'Pipeline to process entries for ownCloud search with connector RelevanceV2',
			'processors' => [
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
			],
		];

		$this->client->expects($this->once())
			->method('request')
			->with('_ingest/pipeline/oc-processor-instanceid-relv2', Request::PUT, $expectedPayload);

		$this->assertNull($this->connectorRelevanceV2->prepareIndex());
	}

	public function testFetchResults() {
		$this->esConfig->expects($this->once())
			->method('getGroupNoContentArray')
			->willReturn([]);
		$this->esConfig->expects($this->once())
			->method('shouldContentBeIncluded')
			->willReturn(true);

		$userObj = $this->createMock(IUser::class);
		$userObj->method('getUID')->willReturn('mockedUser');

		$this->userManager->expects($this->once())
			->method('get')
			->with('mockedUser', true)
			->willReturn($userObj);

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('G1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('G2');
		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($userObj)
			->willReturn([$group1, $group2]);

		$expectedQuery = [
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
												'terms' => [
													'users' => ['mockedUser'],
												]
											],
											[
												'terms' => [
													'groups' => ['G1', 'G2'],
												],
											],
										],
									],
								],
							],
							'should' => [
								[
									'query_string' => [
										'query' =>  'test query',
										'fields' => ['name', 'name.edge_ngram^0.5', 'name.ngram^0.25', 'file.content'],
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
			'fields' => [
				[
					'field' => 'mtime',
					'format' => 'epoch_second',
				]
			],
			'size' => 30,
			'from' => 0,
		];

		$query = $this->createMock(Query::class);
		$query->expects($this->once())
			->method('setRawQuery')
			->with($expectedQuery);

		$this->factory->expects($this->once())
			->method('getNewQuery')
			->willReturn($query);

		$indexMock = $this->createMock(Index::class);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$search = $this->createMock(Search::class);
		$search->expects($this->once())
			->method('addIndex')
			->with($indexMock);

		$this->factory->expects($this->once())
			->method('getNewSearch')
			->with($this->client)
			->willReturn($search);

		// can't perform checks over the result set
		$this->connectorRelevanceV2->fetchResults('mockedUser', 'test query', 30, 0);
	}

	public function testFetchResultsWithoutContent() {
		$this->esConfig->expects($this->once())
			->method('getGroupNoContentArray')
			->willReturn([]);
		$this->esConfig->expects($this->once())
			->method('shouldContentBeIncluded')
			->willReturn(false);

		$userObj = $this->createMock(IUser::class);
		$userObj->method('getUID')->willReturn('mockedUser');

		$this->userManager->expects($this->once())
			->method('get')
			->with('mockedUser', true)
			->willReturn($userObj);

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('G1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('G2');
		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($userObj)
			->willReturn([$group1, $group2]);

		$expectedQuery = [
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
												'terms' => [
													'users' => ['mockedUser'],
												]
											],
											[
												'terms' => [
													'groups' => ['G1', 'G2'],
												],
											],
										],
									],
								],
							],
							'should' => [
								[
									'query_string' => [
										'query' =>  'test query',
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
			'fields' => [
				[
					'field' => 'mtime',
					'format' => 'epoch_second',
				]
			],
			'size' => 30,
			'from' => 0,
		];

		$query = $this->createMock(Query::class);
		$query->expects($this->once())
			->method('setRawQuery')
			->with($expectedQuery);

		$this->factory->expects($this->once())
			->method('getNewQuery')
			->willReturn($query);

		$indexMock = $this->createMock(Index::class);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$search = $this->createMock(Search::class);
		$search->expects($this->once())
			->method('addIndex')
			->with($indexMock);

		$this->factory->expects($this->once())
			->method('getNewSearch')
			->with($this->client)
			->willReturn($search);

		// can't perform checks over the result set
		$this->connectorRelevanceV2->fetchResults('mockedUser', 'test query', 30, 0);
	}

	public function testFetchResultsGroupNoContent() {
		$this->esConfig->expects($this->once())
			->method('getGroupNoContentArray')
			->willReturn(['G1']);
		$this->esConfig->expects($this->once())
			->method('shouldContentBeIncluded')
			->willReturn(true);

		$userObj = $this->createMock(IUser::class);
		$userObj->method('getUID')->willReturn('mockedUser');

		$this->userManager->expects($this->once())
			->method('get')
			->with('mockedUser', true)
			->willReturn($userObj);

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('G1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('G2');
		$this->groupManager->expects($this->once())
			->method('getUserGroups')
			->with($userObj)
			->willReturn([$group1, $group2]);

		$expectedQuery = [
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
												'terms' => [
													'users' => ['mockedUser'],
												]
											],
											[
												'terms' => [
													'groups' => ['G1', 'G2'],
												],
											],
										],
									],
								],
							],
							'should' => [
								[
									'query_string' => [
										'query' =>  'test query',
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
			'fields' => [
				[
					'field' => 'mtime',
					'format' => 'epoch_second',
				]
			],
			'size' => 30,
			'from' => 0,
		];

		$query = $this->createMock(Query::class);
		$query->expects($this->once())
			->method('setRawQuery')
			->with($expectedQuery);

		$this->factory->expects($this->once())
			->method('getNewQuery')
			->willReturn($query);

		$indexMock = $this->createMock(Index::class);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$search = $this->createMock(Search::class);
		$search->expects($this->once())
			->method('addIndex')
			->with($indexMock);

		$this->factory->expects($this->once())
			->method('getNewSearch')
			->with($this->client)
			->willReturn($search);

		// can't perform checks over the result set
		$this->connectorRelevanceV2->fetchResults('mockedUser', 'test query', 30, 0);
	}

	public function testFindInResultId() {
		$result = $this->createMock(Result::class);
		$result->method('getId')->willReturn(987);

		$this->assertSame(987, $this->connectorRelevanceV2->findInResult($result, 'id'));
	}

	public function testFindInResultHighlight() {
		$result = $this->createMock(Result::class);
		$result->method('getHighlights')->willReturn([
			'file.content' => ['high light number 1', 'another high']
		]);

		$this->assertSame(['high light number 1', 'another high'], $this->connectorRelevanceV2->findInResult($result, 'highlights'));
	}

	public function testFindInResultMtime() {
		$result = $this->createMock(Result::class);
		$result->method('getData')->willReturn([
			'mtime' => 123456,
			'size' => 9876,
			'name' => 'a random name',
		]);

		$this->assertSame(123456, $this->connectorRelevanceV2->findInResult($result, 'mtime'));
	}

	public function testDeleteByFileId() {
		$response = $this->createMock(Response::class);
		$response->method('isOk')->willReturn(true);
		$response->method('getStatus')->willReturn(200);

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('deleteById')
			->willReturn($response);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$this->assertTrue($this->connectorRelevanceV2->deleteByFileId(50));
	}

	public function testDeleteByFileIdMissing() {
		$response = $this->createMock(Response::class);
		$response->method('isOk')->willReturn(false);
		$response->method('getStatus')->willReturn(404);

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('deleteById')
			->willReturn($response);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$this->assertTrue($this->connectorRelevanceV2->deleteByFileId(50));
	}

	public function testDeleteByFileIdFailed() {
		$response = $this->createMock(Response::class);
		$response->method('isOk')->willReturn(false);
		$response->method('getStatus')->willReturn(500);

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('deleteById')
			->willReturn($response);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$this->assertFalse($this->connectorRelevanceV2->deleteByFileId(50));
	}

	public function testGetStats() {
		$stats = $this->createMock(Stats::class);
		$stats->method('getData')->willReturn(['nodeCount' => 50]);

		$indexMock = $this->createMock(Index::class);
		$indexMock->expects($this->once())
			->method('getStats')
			->willReturn($stats);

		$this->factory->expects($this->once())
			->method('getNewIndex')
			->willReturn($indexMock);

		$this->assertEquals(['nodeCount' => 50], $this->connectorRelevanceV2->getStats());
	}
}
