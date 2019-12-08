<?php
/**
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Sujith H <sharidasan@owncloud.com>
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

namespace OCA\Search_Elastic\Tests\Unit\Command;

use OC\Files\Node\Folder;
use OCA\Search_Elastic\Command\Rebuild;
use OCA\Search_Elastic\Jobs\UpdateContent;
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Class UpdateTest
 *
 * @package OCA\Search_Elastic\Tests\Unit\Command
 */
class RebuildTest extends TestCase {
	/**
	 * @var CommandTester
	 */
	private $commandTester;

	/**
	 * @var SearchElasticService  | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $searchElasticService;

	/**
	 * @var IUserManager | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $userManager;

	/**
	 * @var IRootFolder | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $rootFolder;

	/**
	 * @var IUser | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $user;

	/**
	 * @var UpdateContent  | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $job;

	/**
	 * Set Up the Test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->searchElasticService = $this->createMock(SearchElasticService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->user = $this->createMock(IUser::class);
		$this->job = $this->createMock(UpdateContent::class);

		$command = new Rebuild($this->searchElasticService, $this->userManager, $this->rootFolder, $this->job);
		$this->commandTester = new CommandTester($command);
	}

	/**
	 * Test no userId provided
	 *
	 * @expectedException RuntimeException
	 *
	 * @return void
	 */
	public function testNoUserId() {
		$this->commandTester->execute([]);
	}

	/**
	 * Test invalid userId provided
	 *
	 * @return void
	 */
	public function testNonExistingUserId() {
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('testuser')
			->willReturn(null);
		$this->rootFolder
			->expects($this->never())
			->method('getUserFolder');
		$this->searchElasticService
			->expects($this->never())
			->method('resetUserIndex');
		$this->commandTester->execute(
			[
				'user_id' => ['testuser'],
				'--force' => true
			]
		);
		$output = $this->commandTester->getDisplay();

		self::assertContains('Unknown user testuser', $output);
	}

	/**
	 * Test valid userId provided
	 *
	 * @return void
	 */
	public function testExistingUserId() {
		$uid = self::getUniqueID();
		$this->user
			->method('getUID')
			->willReturn($uid);
		$folder = $this->createMock(Folder::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->willReturn($this->user);
		$this->rootFolder
			->expects($this->once())
			->method('getUserFolder')
			->willReturn($folder);
		$this->searchElasticService
			->expects($this->once())
			->method('resetUserIndex')
			->willReturn(true);
		$this->job
			->expects($this->once())
			->method('run')
			->willReturn(true);
		$this->commandTester->execute(
			[
				'user_id' => [$uid],
				'--force' => true
			]
		);
		$output = $this->commandTester->getDisplay();

		self::assertContains('Rebuilding Search Index for', $output);
		self::assertContains($uid, $output);
	}

	public function testExistingAndNonExistingUsers() {
		$uid = self::getUniqueID();
		$this->user
			->method('getUID')
			->willReturn($uid);
		$uid2 = $uid . "1";
		$uid3 = $uid . "2";
		$folder = $this->createMock(Folder::class);
		$this->userManager
			->method('get')
			->will($this->returnValueMap([
				[$uid, $this->user],
				[$uid2, null],
				[$uid3, null],
			]));
		$this->rootFolder
			->expects($this->once())
			->method('getUserFolder')
			->willReturn($folder);
		$this->searchElasticService
			->expects($this->once())
			->method('resetUserIndex')
			->willReturn(true);
		$this->job
			->expects($this->once())
			->method('run')
			->willReturn(true);
		$this->commandTester->execute(
			[
				'user_id' => [$uid, $uid2, $uid3],
				'--force' => true
			]
		);
		$output = $this->commandTester->getDisplay();
		$this->assertContains('Rebuilding Search Index for', $output);
		$this->assertContains($uid, $output);
		$this->assertContains("Unknown user $uid2", $output);
		$this->assertContains("Unknown user $uid3", $output);
	}
}
