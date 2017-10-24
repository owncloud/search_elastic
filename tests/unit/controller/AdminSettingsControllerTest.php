<?php
namespace OCA\Search_Elastic\Tests\Unit\Controller;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\SearchElasticService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Test\TestCase;

class AdminSettingsControllerTest extends TestCase {

	/**
	 * @var AdminSettingsController
	 */
	private $controller;

	private $configService;

	private $searchElasticService;

	public function setUp() {
		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->configService = $this->getMockBuilder(SearchElasticConfigService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->searchElasticService = $this->getMockBuilder(SearchElasticService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new AdminSettingsController(
			Application::APP_ID,
			$request,
			$this->configService,
			$this->searchElasticService
		);
	}

	public function testLoadServers() {
		$this->configService->expects($this->once())
			->method('getServers')
			->will($this->returnValue('localhost:9200'));
		$response = $this->controller->loadServers();
		$expected = new JSONResponse([SearchElasticConfigService::SERVERS => 'localhost:9200']);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServers() {
		$this->configService->expects($this->once())
			->method('setServers')
			->with('localhost:9200');
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200');
		$this->assertEquals($expected, $response);
	}
}
