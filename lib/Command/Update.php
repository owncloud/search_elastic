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

namespace OCA\Search_Elastic\Command;

use OC\BackgroundJob\JobList;
use OCA\Search_Elastic\Jobs\SearchJobList;
use OCP\BackgroundJob\IJob;
use OCP\ILogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Update
 *
 * @package OCA\Search_Elastic\Command
 */
class Update extends Command {
	/**
	 * @var JobList
	 */
	private $jobList;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * Update constructor.
	 *
	 * @param SearchJobList $jobList
	 * @param ILogger $logger
	 */
	public function __construct(SearchJobList $jobList, ILogger $logger) {
		$this->jobList = $jobList;
		$this->logger = $logger;
		parent::__construct();
	}

	/**
	 * Command Config and options
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('search:index:update')
			->setDescription('Update the search index by running all pending background jobs.')
			->addOption(
				'quiet',
				'q',
				InputOption::VALUE_NONE,
				'Suppress output'
			);
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	public function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln("Start Updating the Elastic Search index:");

		$updateJobs = [];

		while ($job = $this->jobList->getNext()) {
			if (!$job instanceof IJob) {
				break;
			}
			if (isset($updateJobs[$job->getId()])) {
				$this->jobList->unlockJob($job);
				break;
			}

			$jobName = \get_class($job);
			$output->writeln("Executing: <info>{$job->getId()} - {$jobName}</info>");
			$job->execute($this->jobList, $this->logger);

			// clean up after unclean jobs
			\OC_Util::tearDownFS();

			$this->jobList->setLastJob($job);
			$updateJobs[$job->getId()] = true;
			unset($job);
		}

		if (\count($updateJobs) === 0) {
			$output->writeln("No pending jobs found.");
		}

		$output->writeln('');

		return 0;
	}
}
