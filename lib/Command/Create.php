<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
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
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Command {

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
			->setName('search:index:create')
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
	 * @return int
	 */
	public function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
		} else {
			$users = $input->getArgument('user_id');
		}
		$quiet = $input->getOption('quiet');

		if (\count($users) === 0) {
			$output->writeln('<error>Please specify the user id to index, "--all" to index for all users</error>');
			return 1;
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
		return 0;
	}
}
