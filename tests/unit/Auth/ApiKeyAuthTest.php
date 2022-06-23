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

use OCA\Search_Elastic\Auth\ApiKeyAuth;
use OCA\Search_Elastic\Auth\IAuth;
use OCP\Security\ICredentialsManager;
use Test\TestCase;

class ApiKeyAuthTest extends TestCase {
	/** @var ICredentialsManager */
	private $credentialsManager;

	/** @var ApiKeyAuth */
	private $apiKeyAuth;

	protected function setUp(): void {
		parent::setUp();
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);

		$this->apiKeyAuth = new ApiKeyAuth($this->credentialsManager);
	}

	public function testGetRequiredAuthKeys() {
		$this->assertSame(['apiKey'], $this->apiKeyAuth->getRequiredAuthKeys());
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
		];
	}

	/**
	 * @dataProvider saveAuthParamsExpectFalseProvider
	 */
	public function testSaveAuthParamsExpectFalse(array $authParams) {
		$this->credentialsManager->expects($this->never())
			->method('store');

		$this->assertFalse($this->apiKeyAuth->saveAuthParams($authParams));
	}

	public function saveAuthParamsExpectTrueProvider() {
		return [
			[
				['randomKey' => 'randomValue', 'apiKey' => '1234asString'],
			],
			[
				['apiKey' => 'anApiKeyValue'],
			],
		];
	}

	/**
	 * @dataProvider saveAuthParamsExpectTrueProvider
	 */
	public function testSaveAuthParamsExpectTrue(array $authParams) {
		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('', IAuth::CRED_KEY_PREFIX . 'apiKey', $authParams['apiKey']);

		$this->assertTrue($this->apiKeyAuth->saveAuthParams($authParams));
	}

	public function testGetAuthParams() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->with('', IAuth::CRED_KEY_PREFIX . 'apiKey')
			->willReturn('theGoodKey');

		$this->assertSame(['apiKey' => 'theGoodKey'], $this->apiKeyAuth->getAuthParams());
	}

	public function testGetAuthParamsMissing() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(null);

		$this->assertSame([], $this->apiKeyAuth->getAuthParams());
	}

	public function testClearAuthParams() {
		$this->credentialsManager->expects($this->once())
			->method('delete')
			->with('', IAuth::CRED_KEY_PREFIX . 'apiKey');

		$this->assertNull($this->apiKeyAuth->clearAuthParams());
	}

	public function maskAuthParamsProvider() {
		return [
			[
				[],
				[],
			],
			[
				['apiKey' => 'aRandomValue'],
				['apiKey' => IAuth::MASKED_VALUE],
			],
			[
				['apiKey' => 'aRandomValue', 'anotherKey' => 'anotherValue'],
				['apiKey' => IAuth::MASKED_VALUE],
			],
		];
	}

	/**
	 * @dataProvider maskAuthParamsProvider
	 */
	public function testMaskAuthParams(array $authParams, $expectedResult) {
		$this->assertSame($expectedResult, $this->apiKeyAuth->maskAuthParams($authParams));
	}
}
