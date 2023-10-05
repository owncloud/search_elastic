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

use Elastica\ResultSet;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\ILogger;
use OCP\Files\Node;
use OCA\Search_Elastic\Connectors\IConnector;
use OCA\Search_Elastic\Connectors\Hub;
use Test\TestCase;

class HubTest extends TestCase {
	/** @var SearchElasticConfigService */
	private $esConfig;
	/** @var ILogger */
	private $logger;

	/** @var Hub */
	private $hub;

	protected function setUp(): void {
		parent::setUp();

		$this->esConfig = $this->createMock(SearchElasticConfigService::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->hub = new Hub($this->esConfig, $this->logger);
	}

	public function testGetRegisteredConnectorNames() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->assertEquals(['Con001', 'Con002'], $this->hub->getRegisteredConnectorNames());
	}

	public function testGetRegisteredConnector() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->assertSame($con1, $this->hub->getRegisteredConnector('Con001'));
		$this->assertSame($con2, $this->hub->getRegisteredConnector('Con002'));
	}

	public function testGetRegisteredConnectorMissing() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');

		$this->hub->registerConnector($con1);

		$this->assertNull($this->hub->getRegisteredConnector('Con002'));
	}

	public function testPrepareWriteIndexesIsAllSetup() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->never())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->prepareWriteIndexes());
	}

	public function testPrepareWriteIndexesIsNoneSetup() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturnOnConsecutiveCalls(false, true);
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturnOnConsecutiveCalls(false, true);
		$con2->expects($this->once())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->prepareWriteIndexes());
	}

	public function testPrepareWriteIndexesIsAllSetupMultipleCallsCached() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con2->expects($this->never())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->prepareWriteIndexes());
		$this->assertTrue($this->hub->prepareWriteIndexes());
	}

	public function testPrepareWriteIndexesIsAllSetupForce() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con2->expects($this->once())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->prepareWriteIndexes(true));
	}

	public function testPrepareWriteIndexesIsNoneSetupFail() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(false);  // both calls to isSetup fails
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(false);  // both calls to isSetup fails
		$con2->expects($this->once())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertFalse($this->hub->prepareWriteIndexes());
	}

	public function testPrepareSearchIndexIsSetup() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertTrue($this->hub->prepareSearchIndex());
	}

	public function testPrepareSearchIndexIsNotSetup() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturnOnConsecutiveCalls(false, true);
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertTrue($this->hub->prepareSearchIndex());
	}

	public function testPrepareSearchIndexIsSetupMultipleCallsCached() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertTrue($this->hub->prepareSearchIndex());
		$this->assertTrue($this->hub->prepareSearchIndex());
	}

	public function testPrepareSearchIndexIsSetupForce() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->expects($this->once())
			->method('isSetup')
			->willReturn(true);
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertTrue($this->hub->prepareSearchIndex(true));
	}

	public function testPrepareSearchIndexIsSetupFail() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(false);  // both calls to isSetup fails
		$con1->expects($this->once())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertFalse($this->hub->prepareSearchIndex());
	}

	public function testhubIsSetup() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->never())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);
		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertTrue($this->hub->hubIsSetup());
	}

	public function testhubIsSetupFail() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('prepareIndex');

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(false);
		$con2->expects($this->once())
			->method('prepareIndex');

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);
		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertFalse($this->hub->hubIsSetup());
	}

	public function testHubIndexNode() {
		$node = $this->createMock(Node::class);

		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(true);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->once())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(true);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->hubIndexNode('userId001', $node, true));
	}

	public function testHubIndexNodeFail() {
		$node = $this->createMock(Node::class);

		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(true);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->once())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(false);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertFalse($this->hub->hubIndexNode('userId001', $node, true));
	}

	public function testHubIndexNodeNotPrepared() {
		$node = $this->createMock(Node::class);

		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(true);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(false);
		$con2->expects($this->never())
			->method('indexNode')
			->with('userId001', $node, true)
			->willReturn(true);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertFalse($this->hub->hubIndexNode('userId001', $node, true));
	}

	public function testHubFetchResults() {
		$resultSet = $this->createMock(ResultSet::class);

		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('fetchResults')
			->with('userId001', 'test query', 30, 0)
			->willReturn($resultSet);

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertEquals(['resultSet' => $resultSet, 'connector' => $con1], $this->hub->hubFetchResults('userId001', 'test query', 30, 0));
	}

	public function testHubFetchResultsNotPrepared() {
		$resultSet = $this->createMock(ResultSet::class);

		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(false);
		$con1->expects($this->never())
			->method('fetchResults')
			->with('userId001', 'test query', 30, 0)
			->willReturn($resultSet);

		$con2 = $this->createMock(IConnector::class);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con001');

		$this->assertFalse($this->hub->hubFetchResults('userId001', 'test query', 30, 0));
	}

	public function testHubDeleteByFileId() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('deleteByFileId')
			->with(123)
			->willReturn(true);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->once())
			->method('deleteByFileId')
			->with(123)
			->willReturn(true);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertTrue($this->hub->hubDeleteByFileId(123));
	}

	public function testHubDeleteByFileIdFailed() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('deleteByFileId')
			->with(123)
			->willReturn(true);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->once())
			->method('deleteByFileId')
			->with(123)
			->willReturn(false);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertFalse($this->hub->hubDeleteByFileId(123));
	}

	public function testHubDeleteByFileIdNotPrepared() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->never())
			->method('deleteByFileId')
			->with(123)
			->willReturn(false);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(false);
		$con2->expects($this->never())
			->method('deleteByFileId')
			->with(123)
			->willReturn(true);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);

		$this->assertFalse($this->hub->hubDeleteByFileId(123));
	}

	public function testHubGetStats() {
		$con1 = $this->createMock(IConnector::class);
		$con1->method('getConnectorName')->willReturn('Con001');
		$con1->method('isSetup')->willReturn(true);
		$con1->expects($this->once())
			->method('getStats')
			->willReturn(['key001' => 'value001', 'random' => 'not so random value']);

		$con2 = $this->createMock(IConnector::class);
		$con2->method('getConnectorName')->willReturn('Con002');
		$con2->method('isSetup')->willReturn(true);
		$con2->expects($this->once())
			->method('getStats')
			->willReturn(['key001' => 'awesome', 'random' => 'maybe random value']);

		$this->hub->registerConnector($con1);
		$this->hub->registerConnector($con2);

		$this->esConfig->method('getConfiguredWriteConnectors')->willReturn(['Con001', 'Con002']);
		$this->esConfig->method('getConfiguredSearchConnector')->willReturn('Con002');

		$expectedResult = [
			'Con002' => ['key001' => 'awesome', 'random' => 'maybe random value'],
			'Con001' => ['key001' => 'value001', 'random' => 'not so random value'],
		];
		$this->assertEquals($expectedResult, $this->hub->hubGetStats());
	}
}
