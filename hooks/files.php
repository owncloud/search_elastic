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

namespace OCA\Search_Elastic\Hooks;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Client;
use OCA\Search_Elastic\Db\Status;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\BackgroundJob;
use OCP\Files\File;
use OCP\Files\Folder;

class Files {

	/**
	 * handle for indexing file
	 *
	 * @param string $path
	 */
	const handle_post_write = 'indexFile';

	/**
	 * handle for renaming file
	 *
	 * @param string $path
	 */
	const handle_post_rename = 'renameFile';

	/**
	 * handle for removing file
	 *
	 * @param string $path
	 */
	const handle_delete = 'deleteFile';

	/**
	 * handle for sharing file
	 *
	 * @param string $path
	 */
	const handle_share = 'shareFile';

	/**
	 * handle file writes (triggers reindexing)
	 * 
	 * the file indexing is queued as a background job
	 * 
	 * @param $param array from postWriteFile-Hook
	 */
	public static function indexFile(array $param) {

		$app = new Application();
		$container = $app->getContainer();
		$userId = $container->query('UserId');

		$logger = \OC::$server->getLogger();

		if (!empty($userId)) {

			// mark written file as new
			$home = \OC::$server->getUserFolder($userId);
			$node = $home->get($param['path']);

			/** @var StatusMapper $mapper */
			$mapper = $container->query('StatusMapper');
			$status = $mapper->getOrCreateFromFileId($node->getId());

			// only index files
			if ($node instanceof File) {
				$logger->debug(
					"Hook indexFile: marking as New {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markNew($status);

				//Add Background Job:
				BackgroundJob::registerJob( 'OCA\Search_Elastic\Jobs\IndexJob', array('userId' => $userId) );
			} else {
				$logger->debug(
					"Hook indexFile: marking Skipped {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markSkipped($status);
			}
		} else {
			$logger->debug(
				'Hook indexFile could not determine user when called with param '
				.json_encode($param), ['app' => 'search_elastic']
			);
		}
	}

	/**
	 * deleteFile triggers the removal of any deleted files from the index
	 *
	 * @param $param array from deleteFile-Hook
	 */
	static public function deleteFile(array $param) {
		$app = new Application();
		$container = $app->getContainer();

		/** @var Client $client */
		$client = $container->query('Client');

		/** @var StatusMapper $mapper */
		$mapper = $container->query('StatusMapper');

		$logger = \OC::$server->getLogger();

		$deletedIds = $mapper->getDeleted();
		$count = 0;
		foreach ($deletedIds as $fileId) {
			$logger->debug( 'deleting status for ('.$fileId.') ',
				['app' => 'search_elastic']
			);
			//delete status
			$status = new Status($fileId);
			$mapper->delete($status);
			$count++;

		}
		$logger->debug( 'removed '.$count.' files from status table',
			['app' => 'search_elastic']
		);

		$count = $client->deleteFiles($deletedIds);
		$logger->debug( 'removed '.$count.' files from index',
			['app' => 'search_elastic']
		);

	}

	/**
	 * handle file shares
	 *
	 * @param $param array
	 */
	public static function shareFile(array $param) {

		$app = new Application();
		$container = $app->getContainer();
		$userId = $container->query('UserId');

		if (!empty($userId)) {

			// mark written file as new
			$home = \OC::$server->getUserFolder($userId);
			$node = $home->get($param['path']);

			//Add Background Job:
			\OC::$server->getJobList()->add(
				'OCA\Search_Elastic\Jobs\UpdateAccess', [
					'userId' => $userId,
					'nodeId' => $node->getId()
				]
			);
		} else {
			\OC::$server->getLogger()->debug(
				'Hook indexFile could not determine user when called with param '.json_encode($param),
				['app' => 'search_elastic']
			);
		}

	}

}
