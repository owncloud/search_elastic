<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
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

use OCA\Search_Elastic\SearchElasticService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class Reset
 *
 * @package OCA\Search_Elastic\Command
 */
class Reset extends Command {
	/**
	 * @var SearchElasticService
	 */
	private $searchElasticService;

	/**
	 * Reset constructor.
	 *
	 * @param SearchElasticService $searchElasticService
	 */
	public function __construct(SearchElasticService $searchElasticService) {
		parent::__construct();
		$this->searchElasticService = $searchElasticService;
	}

	/**
	 * Command Config and options
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('search:index:reset')
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Use this option to reset the index without further questions.'
			)
			->setDescription('Reset the index');
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
		if ($input->getOption('force')) {
			$continue = true;
		} else {
			$helper = $this->getHelper('question');
			$question = new ChoiceQuestion(
				'This will delete the whole search index! Do you want to proceed?',
				['no', 'yes'],
				'no'
			);
			$continue = $helper->ask($input, $output, $question) === 'yes';
		}
		if (!$continue) {
			$output->writeln('Aborting.');
			return 0;
		}

		$this->searchElasticService->setup();
		$output->writeln('Search index has been reset.');
		return 0;
	}
}
