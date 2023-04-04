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

namespace OCA\Search_Elastic\Command;

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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * Class Rebuild
 *
 * @package OCA\Search_Elastic\Command
 */
class FillSecondary extends Command {
	/**
	 * @var SearchElasticService
	 */
	private $searchElasticService;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * Rebuild constructor.
	 *
	 * @param SearchElasticService $searchElasticService
	 * @param IUserManager $userManager
	 * @param IRootFolder $rootFolder
	 */
	public function __construct(
		SearchElasticService $searchElasticService,
		IUserManager $userManager,
		IRootFolder $rootFolder
	) {
		parent::__construct();
		$this->searchElasticService = $searchElasticService;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * Command Config and options
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('search:index:fillSecondary')
			->setDescription(
				'Fill a secondary index based on the indexed data we have.'.
				' Files not matching the "indexed" status will be ignored'.
				' This is intended to be used in data migrations, so the connector for this'.
				' secondary index should have been configured as "write connector"'
			)
			->addArgument(
				'connector_name',
				InputArgument::REQUIRED,
				'The name of the connector.'
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
				'Use this option to fill the secondary index without further questions.'
			)
			->addOption(
				'startOver',
				null,
				InputOption::VALUE_NONE,
				'Start indexing from the beginning, not from a previous savepoint.'
			)
			->addOption(
				'chunkSize',
				null,
				InputOption::VALUE_REQUIRED,
				'The savepoint will be updated after processing this number of files.',
				'100'
			);

		$this->setHelperSet(new HelperSet(['question' => new QuestionHelper()]));  // it seems needed for unit tests
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
		$connectorName = $input->getArgument('connector_name');
		$quiet = $input->getOption('quiet');
		$startOver = $input->getOption('startOver');
		$chunkSize = (int)$input->getOption('chunkSize');

		$params = [
			'startOver' => $startOver,
			'chunkSize' => $chunkSize,
		];

		$shouldAbort = true;
		$this->searchElasticService->partialSetup();
		foreach ($users as $user) {
			$userObject = $this->userManager->get($user);
			if ($userObject !== null) {
				if ($shouldAbort && ($shouldAbort = $this->shouldAbort($input, $output))) {
					// the $this->shouldAbort method should be executed only once.
					// If it returns true, the command is aborted (returning -1).
					// If it returns false, the $shouldAbort var will be false, so the
					// next condition won't be evaluated the next time.
					$output->writeln('Aborting.');
					return -1;
				}
				$this->fillSecondary($connectorName, $userObject, $quiet, $output, $params);
			} else {
				$output->writeln("<error>Unknown user $user</error>");
				return -1;
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
		if (!$input->getOption('force')) {
			$helper = $this->getHelper('question');
			$question = new ChoiceQuestion(
				"This will re-index data for selected users based on already-indexed data! Do you want to proceed?",
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
	protected function fillSecondary($connectorName, $user, $quiet, $output, $params) {
		$uid = $user->getUID();
		if (!$quiet) {
			$output->writeln("Filling secondary index for <info>$uid</info>");
		}
		$home = $this->rootFolder->getUserFolder($uid);

		$countParams = [
			'startOver' => $params['startOver'],
		];
		$indexedCount = $this->searchElasticService->getCountFillSecondaryIndex($uid, $home, $connectorName, $countParams);

		$progressBar = new ProgressBar($output);
		$progressBar->start($indexedCount);

		$fillParams = $params;
		$fillParams['callback'] = function ($fileIds) use ($progressBar) {
			$progressBar->advance(\count($fileIds));
		};
		$this->searchElasticService->fillSecondaryIndex($uid, $home, $connectorName, $fillParams);
		$progressBar->finish();
		$output->writeln('');
	}
}
