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

use OCA\Search_Elastic\Jobs\UpdateContent;
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class Rebuild
 *
 * @package OCA\Search_Elastic\Command
 */
class Rebuild extends Command {
	/**
	 * @var SearchElasticService
	 */
	private $searchelasticservice;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var UpdateContent
	 */
	private $job;

	/**
	 * Rebuild constructor.
	 *
	 * @param SearchElasticService $searchElasticService
	 * @param IUserManager $userManager
	 * @param IRootFolder $rootFolder
	 * @param UpdateContent $job
	 */
	public function __construct(
		SearchElasticService $searchElasticService,
		IUserManager $userManager,
		IRootFolder $rootFolder,
		UpdateContent $job
	) {
		parent::__construct();
		$this->searchelasticservice = $searchElasticService;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->job = $job;
	}

	/**
	 * Command Config and options
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('search:index:rebuild')
			->setDescription('Rebuild the search index for a given User. If you want to rebuild the whole index, run "search:index:reset" and then "search:index:build --all"')
			->addArgument(
				'user_id',
				InputArgument::REQUIRED | InputArgument::IS_ARRAY,
				'Provide a userId. This argument is required.'
			)
			->addOption(
				'quiet',
				'q',
				InputOption::VALUE_NONE,
				'Suppress output'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Use this option to rebuild the search index without further questions.'
			);
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 *
	 * @return int
	 */
	public function execute(InputInterface $input, OutputInterface $output) {
		$users = $input->getArgument('user_id');
		$quiet = $input->getOption('quiet');

		foreach ($users as $user) {
			$userObject = $this->userManager->get($user);
			if ($userObject !== null) {
				if ($this->shouldAbort($input, $output)) {
					$output->writeln('Aborting.');
					return -1;
				}
				$this->rebuildIndex($userObject, $quiet, $output);
			} else {
				$output->writeln("<error>Unknown user $user</error>");
			}
		}
	}

	/**
	 * Decides whether the command has to be aborted or not
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool, returns true when the command has to be aborted, else false
	 */
	private function shouldAbort(InputInterface $input, OutputInterface $output) {
		/**
		 * We are using static variable here because this method is private and
		 * the question should be asked once for the user, instead of asking for
		 * each user. So at any point we need to maintain a state to know if the
		 * question was asked or not.
		 */
		static $result = null;

		if (isset($result)) {
			return $result;
		}

		if (!$input->getOption('force')) {
			$helper = $this->getHelper('question');
			$question = new ChoiceQuestion(
				"This will delete all search index data for selected users! Do you want to proceed?",
				['no', 'yes'],
				'no'
			);
			$result = ($helper->ask($input, $output, $question) === 'yes') ? false : true;
		} else {
			$result = false;
		}
		return $result;
	}

	/**
	 * Rebuild the search index for the given User
	 *
	 * @param IUser $user
	 * @param bool $quiet
	 * @param OutputInterface $output
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 *
	 * @return void
	 */
	protected function rebuildIndex($user, $quiet, $output) {
		$uid = $user->getUID();
		if (!$quiet) {
			$output->writeln("Rebuilding Search Index for <info>$uid</info>");
		}
		$home = $this->rootFolder->getUserFolder($uid);
		$this->searchelasticservice->resetUserIndex($home);
		$this->job->run(['userId' => $uid]);
	}
}
