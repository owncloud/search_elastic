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

use OCA\Search_Elastic\Jobs\UpdateContent;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Build extends Command {

	/**
	 * @var IUserManager $userManager
	 */
	private $userManager;

	/**
	 * @var UpdateContent
	 */
	private $job;

	/**
	 * Index constructor.
	 *
	 * @param IUserManager $userManager
	 * @param UpdateContent $job
	 */
	public function __construct(
		IUserManager $userManager,
		UpdateContent $job
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->job = $job;
	}

	/**
	 * Command config, options and arguments
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('search:index:build')
			->setDescription('Create initial Search Index for one or all users. This command could not update the search index correctly after the initial indexing.')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'Will create index for all files of the given user(s)'
			)
			->addOption(
				'quiet',
				'q',
				InputOption::VALUE_NONE,
				'Suppress output'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Will create index for all files of all known users'
			);
	}

	/**
	 * Update Content for a given user
	 *
	 * @param string $user
	 * @param bool $quiet
	 * @param OutputInterface $output
	 *
	 * @return void
	 */
	protected function indexFiles($user, $quiet, OutputInterface $output) {
		if (!$quiet) {
			$output->writeln("Indexing user <info>$user</info>");
		}
		$this->job->run(['userId' => $user]);
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|null|void
	 */
	public function execute(InputInterface $input, OutputInterface $output) {
		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
		} else {
			$users = $input->getArgument('user_id');
		}
		$quiet = $input->getOption('quiet');

		if (\count($users) === 0) {
			$output->writeln('<error>Please specify the user id to index, "--all" to index for all users</error>');
			return;
		}

		foreach ($users as $user) {
			if (\is_object($user)) {
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
