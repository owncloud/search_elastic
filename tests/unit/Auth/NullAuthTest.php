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

use OCA\Search_Elastic\Auth\NullAuth;
use Test\TestCase;

class NullAuthTest extends TestCase {
	/** @var NullAuth */
	private $nullAuth;

	protected function setUp(): void {
		parent::setUp();
		$this->nullAuth = new NullAuth();
	}

	public function testGetRequiredAuthKeys() {
		$this->assertSame([], $this->nullAuth->getRequiredAuthKeys());
	}

	public function authParamsProvider() {
		return [
			[
				[],
			],
			[
				['username' => 'user1', 'password' => 'password'],
			],
			[
				['apiKey' => 'randomApiKey'],
			],
		];
	}

	/**
	 * @dataProvider authParamsProvider
	 */
	public function testSaveAuthParams(array $authParams) {
		$this->assertTrue($this->nullAuth->saveAuthParams($authParams));
	}

	public function testGetAuthParams() {
		$this->assertSame([], $this->nullAuth->getAuthParams());
	}

	public function testClearAuthParams() {
		$this->assertNull($this->nullAuth->clearAuthParams());
	}

	/**
	 * @dataProvider authParamsProvider
	 */
	public function testMaskAuthParams(array $authParams) {
		$this->assertSame([], $this->nullAuth->maskAuthParams($authParams));
	}
}
