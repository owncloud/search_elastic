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
		$response = $this->controller->loadServers();
		$expected = new JSONResponse([
			SearchElasticConfigService::SERVERS => 'localhost:9200',
			SearchElasticConfigService::SERVER_USER => '',
			SearchElasticConfigService::SERVER_PASSWORD => 'examplepass*123test'
		]);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServers() {
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerUser')
			->with('');
		$this->configService->expects($this->once())
			->method('setServerPassword')
			->with('');
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', '', '');
		$this->assertEquals($expected, $response);
	}

	public function testSaveServersWithUsernameAndPassword() {
		$username = 'ñusername';
		$password = 'abcDEF987!"·$%&/()=,-.-;:_<>';
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerUser')
			->with($username);
		$this->configService->expects($this->once())
			->method('setServerPassword')
			->with($password);
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', $username, $password);
		$this->assertEquals($expected, $response);
	}

	public function testSaveServersWithUsernameAndDefaultPassword() {
		$username = 'ñusername';
		$this->configService->expects($this->once())
			->method('setServers')
			->with('http://localhost:9200');  // http is added
		$this->configService->expects($this->once())
			->method('setServerUser')
			->with($username);
		$this->configService->expects($this->never())
			->method('setServerPassword');
		$expected = new JSONResponse();
		$response = $this->controller->saveServers('localhost:9200', $username, AdminSettingsController::DEFAULT_PASS);
		$this->assertEquals($expected, $response);
	}
}
