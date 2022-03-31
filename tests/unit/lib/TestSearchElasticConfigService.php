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

use OC\Security\Crypto;
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\IConfig;

class TestSearchElasticConfigService extends \Test\TestCase {
	private $owncloudConfigService;
	private $crypto;

	/**
	 * @var SearchElasticConfigService
	 */
	private $searchElasticConfigService;

	public function setUp(): void {
		$this->owncloudConfigService = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->searchElasticConfigService = new SearchElasticConfigService(
			$this->owncloudConfigService
		);
		$this->crypt = $this->getMockBuilder(Crypto::class)
			->disableOriginalConstructor()
			->getMock();
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

	public function testParseServersWithEmptyStringReturnsValidArray() {
		$this->crypto->expects($this->once())
			->method('decrypt')
			->willReturn('testpassword');
		$parsedServers = $this->searchElasticConfigService->parseServers();
		$this->assertIsArray($parsedServers);
		$this->assertCount(6, $parsedServers);
		$this->assertEquals('localhost', $parsedServers['host']);
		$this->assertEquals(9200, $parsedServers['port']);
	}
}
