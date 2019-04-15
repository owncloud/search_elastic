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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCA\Search_Elastic\SearchElasticService;

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
			->setDescription('Reset the index');
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
		$app = new Application();
		$container = $app->getContainer();
		/**
		 * @var SearchElasticService $searchElasticService
		 */
		$searchElasticService = $container->query('SearchElasticService');
		$searchElasticService->setup();
	}
}
