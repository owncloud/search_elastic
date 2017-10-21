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

namespace OCA\Search_Elastic\Hooks;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\SearchElasticService;
use OCA\Search_Elastic\Db\Status;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\BackgroundJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;

/**
 * Class Files
 *
 * @package OCA\Search_Elastic\Hooks
 */
class Files {

	/**
	 * handle for indexing file
	 *
	 * @param string $path
	 */
	const handle_post_write = 'contentChanged';

	/**
	 * handle for renaming file
	 *
	 * @param string $path
	 */
	const handle_post_rename = 'metadataChanged';

	/**
	 * handle for sharing file
	 *
	 * @param string $path
	 */
	const handle_share = 'metadataChanged';

	/**
	 * handle for removing file
	 *
	 * @param string $path
	 */
	const handle_delete = 'deleteFile';

	/**
	 * handle file writes (triggers reindexing)
	 * 
	 * the file indexing is queued as a background job
	 * 
	 * @param $param array from postWriteFile-Hook
	 */
	public static function contentChanged(array $param) {

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

			if ($node instanceof File || $node instanceof Folder) {
				$logger->debug(
					"Hook contentChanged: marking as New {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markNew($status);

				if ($node->isShared()) {
					$storage = $node->getStorage();
					$userId = $storage->getOwner($node->getPath()); // is in the public API with 9
					$logger->debug(
						"Hook metadataChanged: resolved owner to $userId",
						['app' => 'search_elastic']
					);
				}

				//Add Background Job:
				\OC::$server->getJobList()->add( 'OCA\Search_Elastic\Jobs\UpdateContent', ['userId' => $userId] );
			} else {
				$logger->debug(
					"Hook contentChanged: marking Skipped {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markSkipped($status);
			}
		} else {
			$logger->debug(
				'Hook contentChanged could not determine user when called with param '
				.json_encode($param), ['app' => 'search_elastic']
			);
		}
	}

	/**
	 * handle file shares
	 *
	 * @param $param array
	 */
	public static function metadataChanged(array $param) {

		$app = new Application();
		$container = $app->getContainer();
		$userId = $container->query('UserId');

		$logger = \OC::$server->getLogger();

		if (!empty($userId)) {

			// mark written file as new
			$home = \OC::$server->getUserFolder($userId);
			if (isset($param['path'])) {
				$node = $home->get($param['path']);
			} else if (isset($param['newpath'])) {
				$node = $home->get($param['newpath']);
			} else if (isset($param['fileSource'])) {
				$nodes = $home->getById($param['fileSource']);
				if (isset($nodes[0])) {
					$node = $nodes[0];
				}
			}
			if (empty($node)) {
				\OC::$server->getLogger()->debug(
					'Hook metadataChanged could not determine node when called with param ' . json_encode($param),
					['app' => 'search_elastic']
				);
				return;
			}

			/** @var StatusMapper $mapper */
			$mapper = $container->query('StatusMapper');
			$status = $mapper->getOrCreateFromFileId($node->getId());

			if ($status->getStatus() === Status::STATUS_NEW) {
				$logger->debug(
					"Hook metadataChanged: file needs content indexing {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
			} else if ($node instanceof Node) {
				$logger->debug(
					"Hook metadataChanged: marking as Metadata Changed {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markMetadataChanged($status);

				if ($node->isShared()) {
					$userId = $node->getOwner()->getUID();
					$logger->debug(
						"Hook metadataChanged: resolved owner to $userId",
						['app' => 'search_elastic']
					);
				}

				if ($node instanceof Folder) {
					//Add Background Job and tell it to also update children of a certain folder:
					\OC::$server->getJobList()->add(
						'OCA\Search_Elastic\Jobs\UpdateMetadata',
						['userId' => $userId, 'folderId' => $node->getId() ]
					);
				} else {
					//Add Background Job:
					\OC::$server->getJobList()->add(
						'OCA\Search_Elastic\Jobs\UpdateMetadata',
						['userId' => $userId]
					);
				}

			} else {
				$logger->debug(
					"Hook metadataChanged: marking Skipped {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markSkipped($status);
			}
		} else {
			\OC::$server->getLogger()->debug(
				'Hook metadataChanged could not determine user when called with param ' . json_encode($param),
				['app' => 'search_elastic']
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

		/** @var SearchElasticService $searchElasticService */
		$searchElasticService = $container->query('SearchElasticService');

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

		$count = $searchElasticService->deleteFiles($deletedIds);
		$logger->debug( 'removed '.$count.' files from index',
			['app' => 'search_elastic']
		);

	}

}
