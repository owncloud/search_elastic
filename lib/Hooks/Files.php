<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Saugat Pachhai <suagatchhetri@outlook.com>
 * @author Sujith H <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace OCA\Search_Elastic\Hooks;

use OCA\Search_Elastic\Application;
use OCA\Search_Elastic\SearchElasticService;
use OCA\Search_Elastic\Db\Status;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;
use OCP\ILogger;
use OCP\Share\Events\AcceptShare;

/**
 * Class Files
 *
 * @package OCA\Search_Elastic\Hooks
 */
class Files {
	/**
	 * handle for renaming file
	 *
	 * @param string $path
	 */
	public const handle_post_rename = 'metadataChanged';

	/**
	 * handle for sharing file
	 *
	 * @param string $path
	 */
	public const handle_share = 'metadataChanged';

	/**
	 * handle for removing file
	 *
	 * @param string $path
	 */
	public const handle_delete = 'deleteFile';

	/**
	 * Check if the path is outside users home folder
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public static function excludeIndex($path) {
		$path = \ltrim($path, '/');
		/**
		 * Making assumption that path has to be $uid/files/
		 * So if the path when exploded doesn't make up to this
		 * pattern, then this method will return true. Else return
		 * false.
		 */
		$splitPath = \explode('/', $path);

		if (\count($splitPath) < 2) {
			//For example if the path has /avatars
			return true;
		}

		//For example if the path has /avatars/12
		if ($splitPath[1] !== 'files') {
			return true;
		}

		$uid = $splitPath[0];
		$userPathStarts = $uid . '/files/';

		/**
		 * Final check if the path has $uid/files/ in it
		 * if so then get the data indexed. Else exclude
		 * them from indexing
		 */
		return !(\strpos($path, $userPathStarts) !== false);
	}

	/**
	 * @param Node $node
	 * @param string $userId This could be either userId of the node or the userId who accepted the remote share
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws StorageNotAvailableException
	 */
	private static function processNode($node, $userId) {
		$app = new Application();
		$container = $app->getContainer();
		$logger = $container->query(ILogger::class);

		if (!empty($userId)) {
			$mapper = $container->query(StatusMapper::class);
			$status = $mapper->getOrCreateFromFileId($node->getId());

			// mark written file as new
			if ($node instanceof File || $node instanceof Folder) {
				$logger->debug(
					"Hook contentChanged: marking as New {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markNew($status);

				if ($node->isShared()) {
					$storage = $node->getStorage();
					$shareUserId = $storage->getOwner($node->getPath()); // is in the public API with 9
					if (\strpos($shareUserId, '@') === false) {
						$userId = $shareUserId;
					}
					$logger->debug(
						"Hook metadataChanged: resolved owner to $userId",
						['app' => 'search_elastic']
					);
				}

				//Add Background Job:
				\OC::$server->getJobList()->add('OCA\Search_Elastic\Jobs\UpdateContent', ['userId' => $userId]);
			} else {
				$logger->debug(
					"Hook contentChanged: marking Skipped {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$mapper->markSkipped($status);
			}
		}
	}

	/**
	 * Handle file writes (triggers reindexing)
	 *
	 * The file indexing is queued as a background job
	 *
	 * @param mixed $params from event
	 *
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 * @throws StorageNotAvailableException
	 */
	public static function contentChanged($params) {
		$result = self::excludeIndex($params['path']);
		if ($result === true) {
			return;
		}

		$node = \OC::$server->getRootFolder()->get($params['path']);
		$userId = $node->getOwner()->getUID();
		if (!empty($userId)) {
			self::processNode($node, $userId);
		} else {
			\OC::$server->getLogger()->debug(
				'Hook contentChanged could not determine user when called with param '
				.\json_encode($params),
				['app' => 'search_elastic']
			);
		}
	}

	/**
	 * Teardown and setup the fs for the user, so that the mount manager adds
	 * and lists the federated share.
	 *
	 * @param AcceptShare $acceptShare
	 */
	public static function federatedShareUpdate(AcceptShare $acceptShare) {
		$share = $acceptShare->getShare();

		/**
		 * We need to tear down and setup the fs, so that the mount manager will
		 * update to have the federated share added.
		 */
		\OC_Util::tearDownFS();
		/**
		 * Setup fs for the currently logged in user.
		 */
		\OC_Util::setupFS();
		$userFolder = \OC::$server->getUserFolder($share['user']);

		if ($userFolder !== null) {
			$node = $userFolder->get($share['name']);
			self::processNode($node, $share['user']);
		} else {
			\OC::$server->getLogger()->debug('Hook federatedShareUpdate could not find key: user in param '
				. \json_encode($share), ['app' => 'search_elastic']);
		}
	}

	/**
	 * Updates the trashbin restore for the search
	 * @param array $params
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws StorageNotAvailableException
	 */
	public static function trashbinRestoreUpdate($params) {
		$user = \OC::$server->getUserSession()->getUser();
		if ($user !== null) {
			$userFolder = \OC::$server->getUserFolder($user->getUID());
			if ($userFolder !== null) {
				$node = $userFolder->get($params['filePath']);
				self::processNode($node, $node->getOwner()->getUID());
			}
		} else {
			\OC::$server->getLogger()->debug('Hook trashbinRestoreUpdate could not update because the user is not logged in. '
				. \json_encode($params), ['app' => 'search_elastic']);
		}
	}

	/**
	 * Updates the version restore for the search
	 *
	 * @param array $params
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws StorageNotAvailableException
	 */
	public static function fileVersionRestoreUpdate($params) {
		$userFolder = \OC::$server->getUserFolder($params['user']);
		if ($userFolder !== null) {
			$node = $userFolder->get($params['path']);
			self::processNode($node, $node->getOwner()->getUID());
		} else {
			\OC::$server->getLogger()->debug("Hook fileVersionRestoreUpdate could not find user: ${params['user']} revision in param "
				. \json_encode($params), ['app' => 'search_elastic']);
		}
	}

	/**
	 * handle file shares
	 *
	 * @param array $param
	 */
	public static function metadataChanged(array $param) {
		$app = new Application();
		$container = $app->getContainer();
		$userId = $container->query('UserId');
		$logger = $container->query(ILogger::class);

		if (!empty($userId)) {

			// mark written file as new
			$home = \OC::$server->getUserFolder($userId);
			if (isset($param['path'])) {
				$node = $home->get($param['path']);
			} elseif (isset($param['newpath'])) {
				$node = $home->get($param['newpath']);
			} elseif (isset($param['fileSource'])) {
				$nodes = $home->getById($param['fileSource']);
				if (isset($nodes[0])) {
					$node = $nodes[0];
				}
			}
			if (empty($node)) {
				\OC::$server->getLogger()->debug(
					'Hook metadataChanged could not determine node when called with param ' . \json_encode($param),
					['app' => 'search_elastic']
				);
				return;
			}

			$mapper = $container->query(StatusMapper::class);
			$status = $mapper->getOrCreateFromFileId($node->getId());

			if ($status->getStatus() === Status::STATUS_NEW) {
				$logger->debug(
					"Hook metadataChanged: file needs content indexing {$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
			} elseif ($node instanceof Node) {
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
			$logger->debug(
				'Hook metadataChanged could not determine user when called with param ' . \json_encode($param),
				['app' => 'search_elastic']
			);
		}
	}

	/**
	 * deleteFile triggers the removal of any deleted files from the index
	 *
	 * @param array $param from deleteFile-Hook
	 */
	public static function deleteFile(array $param) {
		$app = new Application();
		$container = $app->getContainer();

		$searchElasticService = $container->query(SearchElasticService::class);

		$mapper = $container->query(StatusMapper::class);
		$logger = $container->query(ILogger::class);

		$deletedIds = $mapper->getDeleted();
		$logger->debug(
			\count($deletedIds).' fileids need to be removed:'.
			'( '.\implode(';', $deletedIds).' )',
			['app' => 'search_elastic']
		);

		$deletedStatus = $mapper->deleteIds($deletedIds);
		$logger->debug(
			'removed '.$deletedStatus.' files from status table',
			['app' => 'search_elastic']
		);

		$deletedIndex = $searchElasticService->deleteFiles($deletedIds);
		$logger->debug(
			'removed '.$deletedIndex.' files from index',
			['app' => 'search_elastic']
		);
	}
}
