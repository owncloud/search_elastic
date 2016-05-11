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
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\Jobs\UpdateContent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reset extends Command {

	protected function configure() {
		$this
			->setName('search:reset')
			->setDescription('Reset an index')
			->addArgument(
				'index',
				InputArgument::REQUIRED,
				'reset an index. can be "index" or "temp". resetting "index" will also reset the "temp" index'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$index = $input->getArgument('index');
		$app = new Application();
		$container = $app->getContainer();
		/** @var AdminSettingsController $asc */
		$asc = $container->query('AdminSettingsController');
		switch ($index) {
			case 'index':
				$asc->setup();
				break;
			case 'temp':
				$asc->setUpContentExtractionIndex();
				break;
			default:
				$output->writeln("<error>Unknown index $index</error>");
		}

	}

}
