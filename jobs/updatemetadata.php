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
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\Folder;

class UpdateMetadata extends QueuedJob {

	/**
	 * updates changed metadata for file or folder
	 * @param array $arguments
	 */
	public function run($arguments) {
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

				if (isset($arguments['folderId'])) {
					// we need to update access permissions for subfolders
					$nodes = $home->getById($arguments['folderId']);
					if (isset($nodes[0]) && $nodes[0] instanceof Folder) {
						$logger->debug(
							"Job updateMetadata: marking children as Metadata Changed for {$nodes[0]->getPath()} ({$nodes[0]->getId()})",
							['app' => 'search_elastic']
						);

						$children = $nodes[0]->getDirectoryListing();

						do {
							$child = \array_pop($children);
							if ($child !== null) {
								$status = $statusMapper->getOrCreateFromFileId($child->getId());
								$statusMapper->markMetadataChanged($status);
								if ($child instanceof Folder) {
									$children = \array_merge($children, $child->getDirectoryListing());
								}
							}
						} while (!empty($children));
					} else {
						$logger->error(
							"Job updateMetadata: could not resolve node for {$arguments['folderId']}",
							['app' => 'search_elastic']
						);
					}
				}

				$fileIds = $statusMapper->findFilesWhereMetadataChanged($home);

				$logger->debug(
					\count($fileIds)." files of $userId need metadata indexing",
					['app' => 'search_elastic']
				);

				/** @var SearchElasticService $service */
				$service = $container->query('SearchElasticService');
				$service->indexNodes($userId, $fileIds, false);
			} else {
				$logger->debug(
					'could not resolve user home: '.\json_encode($arguments),
					['app' => 'search_elastic']
				);
			}
		} else {
			$logger->debug(
				'did not receive userId in arguments: '.\json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
	}
}
