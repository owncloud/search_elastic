<?php
/**
 * ownCloud
 *
 * @author Michael Barz <mbarz@owncloud.com>
 * @copyright (C) 2019 ownCloud GmbH
 * @license ownCloud Commercial License
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see
 * <https://owncloud.com/licenses/owncloud-commercial/>.
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
	public function setUp() {
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
