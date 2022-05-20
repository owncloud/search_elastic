<?php
/**
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
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
namespace OCA\Search_Elastic\Tests\Unit\Lib;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\IConfig;
use OCP\Security\ICredentialsManager;

class TestSearchElasticConfigService extends \Test\TestCase {
	private $owncloudConfigService;
	private $credentialsManager;

	/**
	 * @var SearchElasticConfigService
	 */
	private $searchElasticConfigService;

	public function setUp(): void {
		$this->owncloudConfigService = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->searchElasticConfigService = new SearchElasticConfigService(
			$this->owncloudConfigService,
			$this->credentialsManager
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
			->willReturn("1");
		$this->assertTrue($this->searchElasticConfigService->getScanExternalStorageFlag());
	}

	public function testGetValueCallsOwncloudConfigServiceWithAppIdExternalStorageFalse() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, $this->searchElasticConfigService::SCAN_EXTERNAL_STORAGE, true)
			->willReturn("");
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

	public function testSetServerUser() {
		$this->owncloudConfigService->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_USER, 'testUser');
		$this->searchElasticConfigService->setServerUser('testUser');
	}

	public function testGetServerUser() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_USER, '')
			->willReturn('testUser0001');
		$this->assertSame('testUser0001', $this->searchElasticConfigService->getServerUser());
	}

	public function testSetServerPassword() {
		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('', $this->anything(), 'MyPassÖrd123');
		$this->searchElasticConfigService->setServerPassword('MyPassÖrd123');
	}

	public function testGetServerPassword() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->with('', $this->anything())
			->willReturn('MyPassÖrd123');
		$this->assertSame('MyPassÖrd123', $this->searchElasticConfigService->getServerPassword());
	}

	public function testGetServerPasswordMissing() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->with('', $this->anything())
			->willReturn(null);
		$this->assertSame('', $this->searchElasticConfigService->getServerPassword());
	}

	public function parseServersProvider() {
		return [
			[
				'10.10.10.10', '', '',
				[
					'servers' => [
						[
							'path' => '10.10.10.10',
							'transport' => 'http',
						],
					],
				],
			],
			[
				'10.10.10.10:9999', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
						],
					],
				],
			],
			[
				'10.10.10.10:9999/mypath', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
							'path' => 'mypath',
						],
					],
				],
			],
			[
				'10.10.10.10:9999/mypath', 'usertest1', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
							'path' => 'mypath',
							'username' => 'usertest1',
							'password' => '',
						],
					],
				],
			],
			[
				'10.10.10.10:9999/mypath', 'usertest1', 'testPassword',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
							'path' => 'mypath',
							'username' => 'usertest1',
							'password' => 'testPassword',
						],
					],
				],
			],
			[
				'10.10.10.10:9999/mypath', '', 'testPassword',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
							'path' => 'mypath',
						],
					],
				],
			],
			[
				'http://10.10.10.10', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'transport' => 'http',
						],
					],
				],
			],
			[
				'https://10.10.10.10', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 443,
							'transport' => 'https',
						],
					],
				],
			],
			[
				'https://10.10.10.10:8888', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 8888,
							'transport' => 'https',
						],
					],
				],
			],
			[
				'https://10.10.10.10:8888/my/path/', '', '',
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 8888,
							'transport' => 'https',
							'path' => 'my/path/',
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider parseServersProvider
	 */
	public function testParseServers($servers, $username, $password, $expected) {
		$this->owncloudConfigService->method('getAppValue')
			->will($this->returnValueMap([
				[Application::APP_ID, SearchElasticConfigService::SERVERS, 'localhost:9200', $servers],
				[Application::APP_ID, SearchElasticConfigService::SERVER_USER, '', $username],
			]));
		$this->credentialsManager->method('retrieve')
			->with('', $this->anything())
			->willReturn($password);
		$this->assertEquals($expected, $this->searchElasticConfigService->parseServers());
	}
}
