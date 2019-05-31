<?php
namespace OCA\Search_Elastic\Tests\Unit\Lib;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\IConfig;

class TestSearchElasticConfigService extends \Test\TestCase {
	private $owncloudConfigService;

	/**
	 * @var SearchElasticConfigService
	 */
	private $searchElasticConfigService;

	public function setUp() {
		$this->owncloudConfigService = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->searchElasticConfigService = new SearchElasticConfigService(
			$this->owncloudConfigService
		);
	}

	public function testSetValueCallsOwncloudConfigServiceWithAppId() {
		$this->owncloudConfigService->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, 'key', 'value');
		$this->searchElasticConfigService->setValue('key', 'value');
	}

	public function testGetValueCallsOwncloudConfigServiceWithAppId() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, 'key', 'default');
		$this->searchElasticConfigService->getValue('key', 'default');
	}

	public function testGetValueCallsOwncloudConfigServiceWithAppIdExternalStorage() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, $this->searchElasticConfigService::SCAN_EXTERNAL_STORAGE, true)
			->willReturn(true);
		$this->assertTrue($this->searchElasticConfigService->getScanExternalStorageFlag());
	}

	public function testGetValueCallsOwncloudConfigServiceWithAppIdExternalStorageFalse() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, $this->searchElasticConfigService::SCAN_EXTERNAL_STORAGE, true)
			->willReturn('false');
		$this->assertFalse($this->searchElasticConfigService->getScanExternalStorageFlag());
	}

	public function testSetUserValueCallsOwncloudConfigServiceWithAppId() {
		$this->owncloudConfigService->expects($this->once())
			->method('setUserValue')
			->with('1', Application::APP_ID, 'key', 'value');
		$this->searchElasticConfigService->setUserValue('1', 'key', 'value');
	}

	public function testGetUserValueCallsOwncloudConfigServiceWithAppId() {
		$this->owncloudConfigService->expects($this->once())
			->method('getUserValue')
			->with('1', Application::APP_ID, 'key', 'default');
		$this->searchElasticConfigService->getUserValue('1', 'key', 'default');
	}

	public function testParseServersWithEmptyStringReturnsValidArray() {
		$parsedServers = $this->searchElasticConfigService->parseServers('');
		$this->assertInternalType('array', $parsedServers);
		$this->assertCount(2, $parsedServers);
		$this->assertEquals('localhost', $parsedServers['host']);
		$this->assertEquals(9200, $parsedServers['port']);
	}
}
