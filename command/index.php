<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2016 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Command;

use \OC\User\Manager;
use OCA\Search_Elastic\Jobs\UpdateContent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Index extends Command {

	/**
	 * @var \OC\User\Manager $userManager
	 */
	private $userManager;

	public function __construct(Manager $userManager) {
		$this->userManager = $userManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('search:index')
			->setDescription('Index one or all users')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'will index all files of the given user(s)'
			)
			->addOption(
				'quiet',
				'q',
				InputOption::VALUE_NONE,
				'suppress output'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'will index all files of all known users'
			);
	}


	protected function indexFiles($user, $quiet, OutputInterface $output) {
		$job = new UpdateContent();
		if (!$quiet) {
			$output->writeln("Indexing user <info>$user</info>");
		}
		$job->run(['userId' => $user]);
	}
	public function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
		} else {
			$users = $input->getArgument('user_id');
		}
		$quiet = $input->getOption('quiet');


		if (count($users) === 0) {
			$output->writeln('<error>Please specify the user id to index, "--all" to index for all users</error>');
			return;
		}

		foreach ($users as $user) {
			if (is_object($user)) {
				$user = $user->getUID();
			}
			if ($this->userManager->userExists($user)) {
				$this->indexFiles($user, $quiet, $output);
			} else {
				$output->writeln("<error>Unknown user $user</error>");
			}
		}
	}

}
