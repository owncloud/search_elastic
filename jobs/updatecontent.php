<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014-2016 ownCloud, Inc.
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
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\Files\Folder;

class UpdateContent extends QueuedJob {

	/**
	 * updates changed content for files
	 * @param array $arguments
	 */
	public function run($arguments){
		$app = new Application();
		$container = $app->getContainer();

		$logger = \OC::$server->getLogger();

		if (isset($arguments['userId'])) {
			$userId = $arguments['userId'];

			// This sets up the correct storage.
			// The db mapper does some magic with the filesystem
			$home = \OC::$server->getUserFolder($userId);

			if ($home instanceof Folder) {

				/** @var StatusMapper $statusMapper */
				$statusMapper = $container->query('StatusMapper');
				$fileIds = $statusMapper->findFilesWhereContentChanged($home);

				$logger->debug(
					count($fileIds)." files of $userId need content indexing",
					['app' => 'search_elastic']
				);

				$container->query('Client')->indexNodes($userId, $fileIds);

			} else {
				$logger->debug(
					'could not resolve user home: '.json_encode($arguments),
					['app' => 'search_elastic']
				);
			}
		} else {
			$logger->debug(
				'did not receive userId in arguments: '.json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
 	}
}
