<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Jobs;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Core\Logger;
use OC\BackgroundJob\QueuedJob;

class IndexJob extends QueuedJob {

	/**
	 * @param array $arguments
	 */
	public function run($arguments){
		$app = new Application();
		$container = $app->getContainer();

		/** @var Logger $logger */
		$logger = $container->query('Logger');

		if (isset($arguments['userId'])) {
			$userId = $arguments['userId'];

			// we use our own fs setup code to also set the user in the session
			$folder = $container->query('FileUtility')->setUpUserHome($userId);

			if ($folder) {

				$fileIds = $container->query('StatusMapper')->getUnindexed();

				$logger->debug('background job indexing '.count($fileIds).' files for '.$userId );

				$container->query('Client')->indexFiles($fileIds);

			}
		} else {
			$logger->debug('indexer job did not receive userId in arguments: '.json_encode($arguments));
		}
 	}
}
