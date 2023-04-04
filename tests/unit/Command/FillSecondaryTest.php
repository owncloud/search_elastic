<?php
/**
 * @copyright Copyright (c) 2023, ownCloud GmbH
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
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use OCA\Search_Elastic\Command\FillSecondary;
use Test\TestCase;

/**
 * Class UpdateTest
 *
 * @package OCA\Search_Elastic\Tests\Unit\Command
 */
class FillSecondaryTest extends TestCase {
	/** @var CommandTester */
	private $commandTester;
	/** @var SearchElasticService */
	private $searchElasticService;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $rootFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->searchElasticService = $this->createMock(SearchElasticService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$command = new FillSecondary($this->searchElasticService, $this->userManager, $this->rootFolder);
		$this->commandTester = new CommandTester($command);
	}

	public function testNoUserId() {
		$this->expectException(\RuntimeException::class);

		$this->commandTester->execute([]);
	}

	public function testConnectorNoUser() {
		$this->expectException(\RuntimeException::class);

		$this->commandTester->execute(['connector_name' => 'con001']);
	}

	public function testMissingUser() {
		$this->userManager->method('get')->willReturn(null);

		$this->searchElasticService->expects($this->once())
			->method('partialSetup');

		$this->commandTester->execute([
			'connector_name' => 'con001',
			'user_id' => ['user001'],
		]);
		$this->assertSame(-1, $this->commandTester->getStatusCode());
	}

	public function testShouldAbort() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user001');

		$this->userManager->expects($this->once())
			->method('get')
			->with('user001')
			->willReturn($user);

		$this->searchElasticService->expects($this->once())
			->method('partialSetup');

		$this->commandTester->setInputs(['no']);
		$this->commandTester->execute([
			'connector_name' => 'con001',
			'user_id' => ['user001'],
		]);
		$this->assertSame(-1, $this->commandTester->getStatusCode());
	}

	public function testShouldAbortMultipleUsers() {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user001');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user002');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user003');

		$this->userManager->expects($this->once())
			->method('get')
			->willReturnMap([
				['user001', false, $user1],
				['user002', false, $user2],
				['user003', false, $user3],
			]);

		$this->searchElasticService->expects($this->once())
			->method('partialSetup');

		$this->commandTester->setInputs(['no']);
		$this->commandTester->execute([
			'connector_name' => 'con001',
			'user_id' => ['user001'],
		]);
		$this->assertSame(-1, $this->commandTester->getStatusCode());
	}

	public function testExecute() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user001');

		$this->userManager->expects($this->once())
			->method('get')
			->with('user001')
			->willReturn($user);

		$this->searchElasticService->expects($this->once())
			->method('partialSetup');

		$folder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user001')
			->willReturn($folder);

		$this->searchElasticService->expects($this->once())
			->method('getCountFillSecondaryIndex')
			->with('user001', $folder, 'con001', ['startOver' => false])
			->willReturn(987);
		$this->searchElasticService->expects($this->once())
			->method('fillSecondaryIndex')
			->with('user001', $folder, 'con001', $this->anything());

		//$this->commandTester->setInputs(['yes']);
		$this->commandTester->execute([
			'connector_name' => 'con001',
			'user_id' => ['user001'],
			'--force' => true,
		]);
		$this->assertSame(0, $this->commandTester->getStatusCode());
	}
}
