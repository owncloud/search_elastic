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

use OC\Command\CommandJob;
use OCA\Files_Trashbin\BackgroundJob\ExpireTrash;
use OCA\Search_Elastic\Jobs\DeleteJob;
use OCA\Search_Elastic\Jobs\SearchJobList;
use OCP\BackgroundJob\IJobList;
use OCP\ILogger;
use OCA\Search_Elastic\Command\Update;
use OCA\Search_Elastic\Jobs\UpdateContent;
use OCA\Search_Elastic\Jobs\UpdateMetadata;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Class UpdateTest
 *
 * @package OCA\Search_Elastic\Tests\Unit\Command
 */
class UpdateTest extends TestCase {
	/**
	 * @var CommandTester
	 */
	private $commandTester;

	/**
	 * @var SearchJobList | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $jobList;

	/**
	 * @var ILogger | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $logger;

	/**
	 * Set Up the Test
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
		$this->jobList = self::createMock(SearchJobList::class);
		$this->logger = self::createMock(ILogger::class);
		$command = new Update($this->jobList, $this->logger);
		$this->commandTester = new CommandTester($command);
	}

	/**
	 * Test with no pending UpdateContent or UpdateMetadata
	 * jobs in the queue
	 *
	 * @return void
	 */
	public function testNoPendingJobs() {
		$this->jobList
			->expects($this->once())
			->method('getNext')
			->willReturn(null);
		$this->jobList
			->expects($this->never())
			->method('setLastJob');

		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();

		self::assertContains('Start Updating the Elastic Search index:', $output);
		self::assertContains('No pending jobs found.', $output);
	}

	/**
	 * Test with one pending UpdateContent job in the queue
	 *
	 * @return void
	 */
	public function testPendingUpdateContentJobs() {
		$updateJob = self::createMock(UpdateContent::class);
		$updateJob->expects($this->once())
			->method('execute')
			->willReturn(true);
		$updateJob
			->method('getId')
			->willReturn(1);
		$this->jobList
			->expects($this->exactly(2))
			->method('getNext')
			->willReturnOnConsecutiveCalls($updateJob, null);
		$this->jobList
			->expects($this->once())
			->method('setLastJob');

		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();

		self::assertContains('Start Updating the Elastic Search index:', $output);
		self::assertContains('Executing', $output);
		self::assertContains(\get_class($updateJob), $output);
		self::assertNotContains('No pending jobs found.', $output);
	}

	/**
	 * Test with one pending UpdateContent and one pending UpdateMetadata job in the queue
	 *
	 * @return void
	 */
	public function testPendingDifferentJobs() {
		$updateJob = self::createMock(UpdateContent::class);
		$updateJob->expects($this->once())
			->method('execute')
			->willReturn(true);
		$updateJob
			->method('getId')
			->willReturn(1);
		$metadataJob = self::createMock(UpdateMetadata::class);
		$metadataJob->expects($this->once())
			->method('execute')
			->willReturn(true);
		$metadataJob
			->method('getId')
			->willReturn(2);
		$this->jobList
			->expects($this->exactly(3))
			->method('getNext')
			->willReturnOnConsecutiveCalls($updateJob, $metadataJob, null);
		$this->jobList
			->expects($this->exactly(2))
			->method('setLastJob');

		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();

		self::assertContains('Start Updating the Elastic Search index:', $output);
		self::assertContains('Executing', $output);
		self::assertContains(\get_class($updateJob), $output);
		self::assertContains(\get_class($metadataJob), $output);
		self::assertNotContains('No pending jobs found.', $output);
	}
}
