<?php
/**
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
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
namespace OCA\Search_Elastic\Tests\Unit\Controller;

use OC\AppFramework\Http;
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\SearchElasticService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\IRequest;
use Test\TestCase;

class AdminSettingsControllerTest extends TestCase {

	/**
	 * @var AdminSettingsController
	 */
	private $controller;

	private $configService;

	private $searchElasticService;

	public function setUp(): void {
		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->configService = $this->getMockBuilder(SearchElasticConfigService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->searchElasticService = $this->getMockBuilder(SearchElasticService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->logger = $this->createMock(ILogger::class);

		$this->controller = new AdminSettingsController(
			Application::APP_ID,
			$request,
			$this->configService,
			$this->searchElasticService,
			$this->logger
		);
	}

	public function testLoadServers() {
		$this->configService->expects($this->once())
			->method('getServers')
			->willReturn('localhost:9200');
		$this->configService->expects($this->once())
			->method('getServerAuth')
			->willReturn(['auth' => 'none', 'authParams' => []]);
		$this->configService->expects($this->once())
			->method('maskServerAuthData')
			->willReturnArgument(0);
		$response = $this->controller->loadServers();
		$expected = new JSONResponse([
			SearchElasticConfigService::SERVERS => 'localhost:9200',
			SearchElasticConfigService::SERVER_AUTH => ['auth' => 'none', 'authParams' => []],
		]);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServers() {
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerAuth')
			->with('none', []);
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', 'none', []);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServersAddedPath() {
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200/elastic');  // http is added
		$this->configService->expects($this->once())
			->method('setServerAuth')
			->with('none', []);
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200/elastic', 'none', []);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServersWithUsernameAndPassword() {
		$username = 'ñusername';
		$password = 'abcDEF987!"·$%&/()=,-.-;:_<>';
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerAuth')
			->with('userPass', ['username' => $username, 'password' => $password]);
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', 'userPass', ['username' => $username, 'password' => $password]);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServersWithApiKey() {
		$apiKey = 'abcDEF987!"·$%&/()=,-.-;:_<>';
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerAuth')
			->with('apiKey', ['apiKey' => $apiKey]);
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', 'apiKey', ['apiKey' => $apiKey]);
		$this->assertEquals($expected, $response);
	}

	public function saveServersWithErrorsProvider() {
		return [
			['http:///server.com', 'none', [],  'The url format is incorrect.'],
			['http://:80', 'none', [], 'The url format is incorrect.'],
			['http://user@:80', 'none', [], 'The url format is incorrect.'],
			['http://user@server.com', 'none', [], 'The url contains components that won\'t be used.'],
			['http://user:pass@server.com', 'none', [], 'The url contains components that won\'t be used.'],
			['http://server.com/elastic?param=value', 'none', [], 'The url contains components that won\'t be used.'],
			['http://server.com/elastic#hash', 'none', [], 'The url contains components that won\'t be used.'],
			['/server.com/elastic', 'none', [], 'The url must contains at least a host.'],
			['svg://server.com/elastic', 'none', [], 'The url contains invalid scheme.'],
		];
	}

	/**
	 * @dataProvider saveServersWithErrorsProvider
	 */
	public function testSaveServersWithErrors($urls, $authType, $authParams, $expectedError) {
		$expectedResponse = new JSONResponse(['message' => $expectedError], Http::STATUS_EXPECTATION_FAILED);
		$this->assertEquals($expectedResponse, $this->controller->saveServers($urls, $authType, $authParams));
	}
}
