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
use OCA\Search_Elastic\Auth\AuthManager;
use OCA\Search_Elastic\Auth\IAuth;
use OCP\IConfig;
use OCP\Security\ICredentialsManager;

class TestSearchElasticConfigService extends \Test\TestCase {
	private $owncloudConfigService;
	private $credentialsManager;
	private $authManager;

	/**
	 * @var SearchElasticConfigService
	 */
	private $searchElasticConfigService;

	public function setUp(): void {
		$this->owncloudConfigService = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->authManager = $this->createMock(AuthManager::class);

		$this->searchElasticConfigService = new SearchElasticConfigService(
			$this->owncloudConfigService,
			$this->credentialsManager,
			$this->authManager
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

	public function testSetServerAuth() {
		$authParams = [
			'name' => 'randomString',
			'magic' => '123abc',
		];

		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, '')
			->willReturn('');

		$namedAuthMock = $this->createMock(IAuth::class);
		$this->authManager->method('getAuthByName')
			->will($this->returnValueMap([
				['', null],
				['named', $namedAuthMock],
			]));

		$this->owncloudConfigService->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, 'named');

		$namedAuthMock->expects($this->once())
		->method('saveAuthParams')
		->with($authParams);

		$this->searchElasticConfigService->setServerAuth('named', $authParams);
	}

	public function testSetServerAuthWithOld() {
		$authParams = [
			'name' => 'randomString',
			'magic' => '123abc',
		];

		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, '')
			->willReturn('old');

		$oldAuthMock = $this->createMock(IAuth::class);
		$namedAuthMock = $this->createMock(IAuth::class);
		$this->authManager->method('getAuthByName')
			->will($this->returnValueMap([
				['old', $oldAuthMock],
				['named', $namedAuthMock],
			]));

		$this->owncloudConfigService->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, 'named');

		$oldAuthMock->expects($this->once())
			->method('clearAuthParams');

		$namedAuthMock->expects($this->once())
		->method('saveAuthParams')
		->with($authParams);

		$this->searchElasticConfigService->setServerAuth('named', $authParams);
	}

	public function testGetServerAuthEmpty() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, '')
			->willReturn('');

		$this->assertEquals(['auth' => '', 'authParams' => []], $this->searchElasticConfigService->getServerAuth());
	}

	public function testGetServerAuth() {
		$this->owncloudConfigService->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, '')
			->willReturn('named');

		$namedAuthMock = $this->createMock(IAuth::class);
		$this->authManager->method('getAuthByName')
			->will($this->returnValueMap([
				['named', $namedAuthMock],
			]));

		$namedAuthMock->expects($this->once())
			->method('getAuthParams')
			->willReturn(['name' => 'ooo', 'magic' => '123abc']);

		$expected = [
			'auth' => 'named',
			'authParams' => ['name' => 'ooo', 'magic' => '123abc'],
		];
		$this->assertSame($expected, $this->searchElasticConfigService->getServerAuth());
	}

	public function testMaskServerAuthData() {
		$authObj = $this->createMock(IAuth::class);
		$authObj->expects($this->once())
			->method('maskAuthParams')
			->will($this->returnCallback(function ($params) {
				return \array_map(function ($elem) {
					return "_{$elem}_";
				}, $params);
			}));

		$this->authManager->expects($this->once())
			->method('getAuthByName')
			->with('named')
			->willReturn($authObj);

		$authData = [
			'auth' => 'named',
			'authParams' => [
				'username' => 'user001',
				'password' => 'pass009',
			],
		];
		$expectedResult = [
			'auth' => 'named',
			'authParams' => [
				'username' => '_user001_',
				'password' => '_pass009_',
			],
		];

		$this->assertEquals($expectedResult, $this->searchElasticConfigService->maskServerAuthData($authData));
	}

	public function testMaskServerAuthDataMissingAuth() {
		$this->authManager->expects($this->once())
			->method('getAuthByName')
			->with('missing')
			->willReturn(null);

		$authData = [
			'auth' => 'missing',
			'authParams' => [
				'username' => 'user001',
				'password' => 'pass009',
			],
		];
		$expectedResult = [
			'auth' => 'missing',
			'authParams' => [
				'username' => 'user001',
				'password' => 'pass009',
			],
		];

		$this->assertEquals($expectedResult, $this->searchElasticConfigService->maskServerAuthData($authData));
	}

	public function parseServersProvider() {
		return [
			[
				'10.10.10.10', '', [],
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
				'10.10.10.10:9999', '', [],
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
				'10.10.10.10:9999/mypath', '', [],
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
				'10.10.10.10:9999/mypath', 'userPass', ['username' => 'usertest1', 'password' => ''],
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
				'10.10.10.10:9999/mypath', 'userPass', ['username' => 'usertest1', 'password' => 'testPassword'],
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
				'10.10.10.10:9999/mypath', 'apiKey', ['apiKey' => 'aRandomApiKey007'],
				[
					'servers' => [
						[
							'host' => '10.10.10.10',
							'port' => 9999,
							'transport' => 'http',
							'path' => 'mypath',
							'headers' => [
								'Authorization' => 'ApiKey aRandomApiKey007',
							],
						],
					],
				],
			],
			[
				'http://10.10.10.10', '', [],
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
				'https://10.10.10.10', '', [],
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
				'https://10.10.10.10:8888', '', [],
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
				'https://10.10.10.10:8888/my/path/', '', [],
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
	public function testParseServers($servers, $auth, $authParams, $expected) {
		$authMock = $this->createMock(IAuth::class);
		$authMock->method('getAuthParams')
			->willReturn($authParams);

		$this->owncloudConfigService->method('getAppValue')
			->will($this->returnValueMap([
				[Application::APP_ID, SearchElasticConfigService::SERVERS, 'localhost:9200', $servers],
				[Application::APP_ID, SearchElasticConfigService::SERVER_AUTH, '', $auth],
			]));

		$this->authManager->method('getAuthByName')
			->with($auth)
			->willReturn($authMock);
		$this->assertEquals($expected, $this->searchElasticConfigService->parseServers());
	}
}
