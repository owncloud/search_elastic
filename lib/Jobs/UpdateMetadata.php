<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Phil Davis <phil@jankaritech.com>
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

namespace OCA\Search_Elastic\Jobs;

use OCA\Search_Elastic\AppInfo\Application;
use OC\BackgroundJob\QueuedJob;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\SearchElasticService;
use OCP\Files\Folder;

class UpdateMetadata extends QueuedJob {
	/**
	 * updates changed metadata for file or folder
	 *
	 * @param array $arguments
	 *
	 * @return void
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
				$statusMapper = $container->query(StatusMapper::class);

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

				$service = $container->query(SearchElasticService::class);
				$service->partialSetup();  // prepare all the needed indexes if not done yet
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
