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

use OCA\Search_Elastic\AppInfo\Application;
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

		$app = new Application();
		$container = $app->getContainer();
		/**
		 * @var SearchElasticService $searchElasticService
		 */
		$searchElasticService = $container->query('SearchElasticService');
		$searchElasticService->setup();
		$output->writeln('Search index has been reset.');
	}
}
