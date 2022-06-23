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

use OCA\Search_Elastic\Auth\AuthManager;
use OCA\Search_Elastic\Auth\IAuth;
use Test\TestCase;

class AuthManagerTest extends TestCase {
	/** @var AuthManager */
	private $authManager;

	protected function setUp(): void {
		parent::setUp();
		$this->authManager = new AuthManager();
	}

	public function testSetAndGet() {
		$authMock = $this->createMock(IAuth::class);

		$this->authManager->registerAuthMech('customAuth', $authMock);
		$this->assertSame($authMock, $this->authManager->getAuthByName('customAuth'));
	}

	public function testGetMissing() {
		$authMock = $this->createMock(IAuth::class);

		$this->authManager->registerAuthMech('customAuth', $authMock);
		$this->assertNull($this->authManager->getAuthByName('thisIsMissing'));
	}

	public function testGetMissingNoRegistration() {
		$this->assertNull($this->authManager->getAuthByName('customAuth'));
	}
}
