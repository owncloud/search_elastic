<?php
/**
 * @author Juan Pablo Villafáñez <jvillafanez@owncloud.com>
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
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

namespace OCA\Search_Elastic\Tests\Unit\Auth;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Auth\UserPassAuth;
use OCA\Search_Elastic\Auth\IAuth;
use OCP\Security\ICredentialsManager;
use OCP\IConfig;
use Test\TestCase;

class UserPassAuthTest extends TestCase {
	/** @var ICredentialsManager */
	private $credentialsManager;
	/** @var IConfig */
	private $config;
	/** @var UserPassAuth */
	private $userPassAuth;

	protected function setUp(): void {
		parent::setUp();
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->config = $this->createMock(IConfig::class);

		$this->userPassAuth = new UserPassAuth($this->credentialsManager, $this->config);
	}

	public function testGetRequiredAuthKeys() {
		$this->assertSame(['username', 'password'], $this->userPassAuth->getRequiredAuthKeys());
	}

	public function saveAuthParamsExpectFalseProvider() {
		return [
			[
				[],
			],
			[
				['randomKey' => 'randomValue'],
			],
			[
				['randomKey' => 'randomValue', 'anotherKey' => 'anotherValue'],
			],
			[
				['apiKey' => 1234],
			],
			[
				['username' => 'user'],  // missing password
			],
			[
				['username' => 'user', 'anotherKey' => 'uuu'],  // missing password
			],
			[
				['password' => 'password'],  // missing username
			],
			[
				['password' => 'password', 'anotherKey' => 'ooo'],  // missing username
			],
			[
				['username' => 1234, 'password' => 56789],  // not strings
			],
		];
	}

	/**
	 * @dataProvider saveAuthParamsExpectFalseProvider
	 */
	public function testSaveAuthParamsExpectFalse(array $authParams) {
		$this->credentialsManager->expects($this->never())
			->method('store');
		$this->config->expects($this->never())
			->method('setAppValue');

		$this->assertFalse($this->userPassAuth->saveAuthParams($authParams));
	}

	public function saveAuthParamsExpectTrueProvider() {
		return [
			[
				['randomKey' => 'randomValue', 'username' => 'user1', 'password' => '1234asString'],
			],
			[
				['username' => 'user1', 'password' => 'anPasswordValue'],
			],
		];
	}

	/**
	 * @dataProvider saveAuthParamsExpectTrueProvider
	 */
	public function testSaveAuthParamsExpectTrue(array $authParams) {
		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('', IAuth::CRED_KEY_PREFIX . 'password', $authParams['password']);

		$this->config->expects($this->once())
			->method('setAppValue')
			->with(Application::APP_ID, IAuth::CONF_KEY_PREFIX . 'username', $authParams['username']);

		$this->assertTrue($this->userPassAuth->saveAuthParams($authParams));
	}

	public function testGetAuthParams() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->with('', IAuth::CRED_KEY_PREFIX . 'password')
			->willReturn('theGoodPassword');

		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APP_ID, IAuth::CONF_KEY_PREFIX . 'username', null)
			->willReturn('myUser');

		$this->assertSame(['username' => 'myUser', 'password' => 'theGoodPassword'], $this->userPassAuth->getAuthParams());
	}

	public function getAuthParamsMissingProvider() {
		return [
			['myUser', null, ['username' => 'myUser']],
			[null, 'myPassword', ['password' => 'myPassword']],
			[null, null, []],
		];
	}

	/**
	 * @dataProvider getAuthParamsMissingProvider
	 */
	public function testGetAuthParamsMissing($username, $password, $expectedResult) {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn($password);

		$this->config->expects($this->once())
			->method('getAppValue')
			->willReturn($username);

		$this->assertSame($expectedResult, $this->userPassAuth->getAuthParams());
	}

	public function testClearAuthParams() {
		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('', IAuth::CRED_KEY_PREFIX . 'password');

		$this->config->expects($this->once())
			->method('deleteAppValue')
			->with(Application::APP_ID, IAuth::CONF_KEY_PREFIX . 'username');

		$this->assertNull($this->userPassAuth->clearAuthParams());
	}

	public function maskAuthParamsProvider() {
		return [
			[
				[],
				[],
			],
			[
				['username' => 'aRandomValue', 'password' => 'myPassword'],
				['username' => 'aRandomValue', 'password' => IAuth::MASKED_VALUE],
			],
			[
				['username' => 'aRandomValue', 'password' => 'myPassword', 'anotherKey' => 'anotherValue'],
				['username' => 'aRandomValue', 'password' => IAuth::MASKED_VALUE],
			],
		];
	}

	/**
	 * @dataProvider maskAuthParamsProvider
	 */
	public function testMaskAuthParams(array $authParams, $expectedResult) {
		$this->assertSame($expectedResult, $this->userPassAuth->maskAuthParams($authParams));
	}
}
