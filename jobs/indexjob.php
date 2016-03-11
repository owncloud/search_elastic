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
use OC\BackgroundJob\QueuedJob;

class IndexJob extends QueuedJob {

	/**
	 * @param array $arguments
	 */
	public function run($arguments){
		$app = new Application();
		$container = $app->getContainer();

		$logger = \OC::$server->getLogger();

		if (isset($arguments['userId'])) {
			$userId = $arguments['userId'];

			// This sets up the correct storage. The db mapper does some magic with the filesystem
			$home = \OC::$server->getUserFolder($userId);

			if ($home) {

				$fileIds = $container->query('StatusMapper')->getUnindexed($home);

				$logger->debug(
					'background job indexing '.count($fileIds).' files for '.$userId,
					['app' => 'search_elastic']
				);

				$container->query('Client')->indexFiles($userId, $fileIds);

			}
		} else {
			$logger->debug(
				'indexer job did not receive userId in arguments: '.json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
 	}
}
