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
use OCA\Search_Elastic\Core\Logger;
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

		if (!empty($userId)) {

			// mark written file as new
			/** @var Folder $userFolder */
			$userFolder = $container->query('UserFolder');
			$node = $userFolder->get($param['path']);

			/** @var StatusMapper $mapper */
			$mapper = $container->query('StatusMapper');
			$status = $mapper->getOrCreateFromFileId($node->getId());

			// only index files
			if ($node instanceof File) {
				$mapper->markNew($status);

				//Add Background Job:
				BackgroundJob::registerJob( 'OCA\Search_Elastic\Jobs\IndexJob', array('userId' => $userId) );
			} else {
				$mapper->markSkipped($status);
			}
		} else {
			$container->query('Logger')->debug(
				'Hook indexFile could not determine user when called with param '.json_encode($param)
			);
		}
	}

	/**
	 * handle file renames (triggers indexing and deletion)
	 * 
	 * @param $param array from postRenameFile-Hook
	 */
	public static function renameFile(array $param) {
		//FIXME ... update only name & path of file
		/*
			curl -XPOST 'localhost:9200/test/type1/1/_update' -d '{
				"doc" : {
					"name" : "new_name"
				},
				"detect_noop": true
			}'
		 */
		$app = new Application();
		$container = $app->getContainer();

		if (!empty($param['oldpath'])) {
			//delete from lucene index
			$container->query('Index')->deleteFile($param['oldpath']);
		}

		if (!empty($param['newpath'])) {
			/** @var Folder $userFolder */
			$userFolder = $container->query('UserFolder');
			$node = $userFolder->get($param['newpath']);

			// only index files
			if ($node instanceof File) {
				$mapper = $container->query('StatusMapper');
				$mapper->getOrCreateFromFileId($node->getId());
				self::indexFile(array('path'=>$param['newpath']));
			}

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

		/** @var Logger $logger */
		$logger = $container->query('Logger');

		$deletedIds = $mapper->getDeleted();
		$count = 0;
		foreach ($deletedIds as $fileId) {
			$logger->debug( 'deleting status for ('.$fileId.') ' );
			//delete status
			$status = new Status($fileId);
			$mapper->delete($status);
			$count++;

		}
		$logger->debug( 'removed '.$count.' files from status table' );

		$count = $client->deleteFiles($deletedIds);
		$logger->debug( 'removed '.$count.' files from index' );

	}

	/**
	 * handle file shares
	 *
	 * @param $param array
	 */
	public static function shareFile(array $param) {
		$app = new Application();
		$container = $app->getContainer();

		/** @var Client $client */
		$client = $container->query('Client');

		$client->updateFile($param['fileSource']);

	}

}
