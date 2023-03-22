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
			->setDescription(
				'Rebuild the indexes for a given User.'.
				' All the indexes associated with the configured connectors will be rebuilt.'.
				' This won\'t apply any change to the configuration of the index if it\'s already setup'.
				' but it will setup any index that hasn\'t been setup yet'.
				' Check "search:index:reset" to reset all the indexes associated to the configured connectors'
			)
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
	public function execute(InputInterface $input, OutputInterface $output): int {
		$users = $input->getArgument('user_id');
		$quiet = $input->getOption('quiet');

		foreach ($users as $user) {
			$userObject = $this->userManager->get($user);
			if ($userObject !== null) {
				if ($this->shouldAbort($input, $output)) {
					$output->writeln('Aborting.');
					return 1;
				}
				$this->rebuildIndex($userObject, $quiet, $output);
			} else {
				$output->writeln("<error>Unknown user $user</error>");
			}
		}
		return 0;
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
