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

namespace OCA\Search_Elastic;

use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Connectors\Hub;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\ILogger;

class SearchElasticService {
	/**
	 * @var StatusMapper
	 */
	private $mapper;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var SearchElasticConfigService
	 */
	private $config;

	/**
	 * @var Hub
	 */
	private $hub;

	/**
	 * searchelasticservice Constructor
	 *
	 * @param StatusMapper $mapper
	 * @param ILogger $logger
	 * @param Hub $hub
	 * @param SearchElasticConfigService $config
	 */
	public function __construct(
		StatusMapper $mapper,
		ILogger $logger,
		Hub $hub,
		SearchElasticConfigService $config
	) {
		$this->mapper = $mapper;
		$this->logger = $logger;
		$this->hub = $hub;
		$this->config = $config;
	}

	/**
	 * Resets all the write and search indexes and clears mapping
	 */
	public function fullSetup() {
		$this->hub->clearConnectorsCheckedCache();
		$this->hub->prepareWriteIndexes(true);
		$this->hub->prepareSearchIndex(true);
		$this->mapper->clear();
	}

	/**
	 * Prepare the write and search indexes that haven't been setup
	 * yet. This method WON'T clear the mapping
	 */
	public function partialSetup() {
		$this->hub->prepareWriteIndexes();
		$this->hub->prepareSearchIndex();
	}

	/**
	 * Get whether the connector hub is setup or not. This implies
	 * checking all the configured write and search connectors.
	 * @return bool
	 */
	public function isSetup() {
		return $this->hub->hubIsSetup();
	}

	/**
	 * Get the stats coming from the hub. An array will be returned
	 * contaning all the information.
	 * ```
	 * [
	 *   'connectors' => [
	 *     'Legacy' => [.....],
	 *     'CTest' => [.....],
	 *   ],
	 *   'oc_index' => [.....],
	 *   'countIndexed' => 1234,
	 * ]
	 * ```
	 * The oc_index key is kept for backwards-compatibility.
	 * The countIndexed returns the number if indexed files we have
	 * tracked in our DB, it also there for backwards-compatibility
	 * @return array
	 */
	public function getStats() {
		$stats = $this->hub->hubGetStats();
		$countIndexed = $this->mapper->countIndexed();

		$finalStats = ['connectors' => []];
		foreach ($stats as $key => $stat) {
			// first will be the search connector
			// set its info in the oc_index for compatibility
			if (!isset($finalStats['oc_index'])) {
				$indicesStats = $stat['indices'];
				$firstIndexStats = \reset($indicesStats);
				$finalStats['oc_index'] = $firstIndexStats;
			}
			$finalStats['connectors'][$key] = $stat;
		}
		$finalStats['countIndexed'] = $countIndexed;
		return $finalStats;
	}

	// === CONTENT CHANGES / FULL INDEXING ====================================
	/**
	 * @param string $userId
	 * @param int[] $fileIds
	 * @param bool $extractContent
	 */
	public function indexNodes($userId, array $fileIds, $extractContent = true) {
		foreach ($fileIds as $id) {
			$fileStatus = $this->mapper->getOrCreateFromFileId($id);
			$path = 'unresolved';
			try {
				// before we start mark the file as error so we know there
				// was a problem in case the php execution dies and we don't try
				// the file again
				$this->mapper->markError($fileStatus);

				$node = $this->getNodeForId($userId, $id);

				$path = $node->getPath();

				$skippedDirs = $this->config->getUserSkippedDirs($userId);

				foreach ($skippedDirs as $skippedDir) {
					if (\strpos($path, '/' . $skippedDir . '/') !== false //contains dir
						|| \strrpos($path, '/' . $skippedDir) === \strlen($path) - (\strlen($skippedDir) + 1) // ends with dir
					) {
						throw new SkippedException("dir $path ($id) matches filter '$skippedDir'");
					}
				}

				if ($this->hub->hubIndexNode($userId, $node, $extractContent)) {
					$this->mapper->markIndexed($fileStatus);
				} else {
					$this->mapper->markError($fileStatus, 'Index failed');
				}
			} catch (VanishedException $e) {
				$this->logger->debug("indexFiles: ($id) Vanished", ['app' => 'search_elastic']);
				$fileStatus->setMessage('File vanished');
				$this->mapper->markVanished($fileStatus);
			} catch (NotIndexedException $e) {
				$this->logger->debug("indexFiles: $path ($id) Not indexed", ['app' => 'search_elastic']);
				$fileStatus->setMessage('Not indexed');
				$this->mapper->markUnIndexed($fileStatus);
			} catch (SkippedException $e) {
				$this->logger->debug("indexFiles: $path ($id) Skipped", ['app' => 'search_elastic']);
				$this->logger->debug($e->getMessage(), ['app' => 'search_elastic']);
				$this->mapper->markSkipped($fileStatus, 'Skipped');
			} catch (\Exception $e) {
				//sqlite might report database locked errors when stock filescan is in progress
				//this also catches db locked exception that might come up when using sqlite
				$this->logger->logException($e, ['app' => 'search_elastic']);

				$this->mapper->markError($fileStatus, \substr($e->getMessage(), 0, 255));
				// TODO Add UI to trigger rescan of files with status 'E'rror?
			}
		}

		//$this->index->forcemerge();
	}

	// === DELETE =============================================================
	/**
	 * @param array $fileIds
	 * @return int
	 */
	public function deleteFiles(array $fileIds) {
		if (\count($fileIds) > 0) {
			$count = 0;
			foreach ($fileIds as $fileId) {
				$result = $this->hub->hubDeleteByFileId($fileId);
				if ($result) {
					$count++;
				}
			}
			return $count;
		}
		return 0;
	}

	// === UTILITY ============================================================
	/**
	 * @param string $userId
	 * @param int $fileId
	 * @return Node
	 * @throws NotIndexedException
	 * @throws VanishedException
	 */
	public function getNodeForId($userId, $fileId) {
		/* @var Node[] */
		$userFolder = \OC::$server->getUserFolder($userId);
		if ($userFolder->getId() === $fileId) {
			throw new NotIndexedException();
		}

		$nodes = $userFolder->getById($fileId);
		// getById can return more than one id because the containing storage might be mounted more than once
		// Since we only want to index the file once, we only use the first entry

		if (isset($nodes[0])) {
			$this->logger->debug(
				"getFileForId: $fileId -> node {$nodes[0]->getPath()} ({$nodes[0]->getId()})",
				['app' => 'search_elastic']
			);
			$node = $nodes[0];
		} else {
			throw new VanishedException((string)$fileId);
		}

		if ($node instanceof File || $node instanceof Folder) {
			return $node;
		}
		throw new NotIndexedException();
	}

	/**
	 * Reset all Files to status NEW in a given users home folder
	 *
	 * @param Node $home
	 *
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 *
	 * @return void
	 */
	public function resetUserIndex($home) {
		if ($home instanceof Folder) {
			$this->logger->debug(
				"Command Rebuild Search Index: marking all Files for User {$home->getOwner()->getUID()} as New.",
				['app' => 'search_elastic']
			);

			$children = $home->getDirectoryListing();

			do {
				$child = \array_pop($children);
				if ($child !== null) {
					$status = $this->mapper->getOrCreateFromFileId($child->getId());
					$this->mapper->markNew($status);
					if ($child instanceof Folder) {
						$children = \array_merge($children, $child->getDirectoryListing());
					}
				}
			} while (!empty($children));
		} else {
			$this->logger->error(
				"Command Rebuild Search Index: could not resolve node for {$home->getPath()}",
				['app' => 'search_elastic']
			);
		}
	}

	/**
	 * Count the number of indexed nodes that will be used to fill the secondary index.
	 * Since filling the index can take a lot of time, savepoints are used so we can
	 * continue from a previous savepoint if something goes wrong. By default, we'll
	 * count from the latest known savepoint.
	 *
	 * The $params parameter can contain the following options:
	 * - 'startOver' (optional, default false) -> bool whether this method should count
	 * from the beginning (true) or should start from a previous savepoint (false)
	 *
	 * @param string $userId the userId of the owner of the home
	 * @param Folder $home the home folder to be indexed
	 * @param string $connectorName the name of the connector to use
	 * @param array $params a map of options for this method, as explained above
	 */
	public function getCountFillSecondaryIndex($userId, $home, $connectorName, $params = []) {
		$minId = 0;
		$startOver = $params['startOver'] ?? false;
		$adlerUser = \hash('adler32', $userId); // hash the user to ensure it fits in the config key

		if (!$startOver) {
			$minId = (int)$this->config->getValue("es_fillsec_{$connectorName}_{$adlerUser}", '0');
		}

		return $this->mapper->countFilesIndexed($home, $minId);
	}

	/**
	 * Fill a secondary index by indexing the data from the home directory using the
	 * connector provided by name.
	 *
	 * The $params parameter can contain the following options:
	 * - 'startOver' (optional, default false) -> bool, whether this method should count
	 * from the beginning (true) or should start from a previous savepoint (false)
	 * - 'chunkSize' (optional, default 100) -> int, the chunk size of the list of files.
	 * After processing those files, the savepoint will be updated and more files will
	 * be requested to be indexed. The callback (if any) will be called after each chunk
	 * has been fully processed.
	 * - 'callback' (optional, default null) -> callback(array $fileIds), a callback to
	 * be called after a chunk of files has been processed. This is intended to provide
	 * a way to show progress.
	 *
	 * Filling the secondary index is expected to take a lot of time. We'll use savepoints
	 * in order to continue from that point if something goes wrong, and avoid having to
	 * re-index everything from the beginning.
	 *
	 * By default, it will start indexing from the previously savepoint (if any).
	 * Note that this doesn't guarantee the files will be indexed only once. If the app
	 * crashes, some of the files of the last chunk might have been indexed before the
	 * crash, so those will be re-indexed when we resume the operation.
	 * What it does guarantee is that not all the chunks will be re-indexed, just the
	 * last one.
	 *
	 * @param string $userId the userId of the owner of the home
	 * @param Folder $home the home folder to be indexed
	 * @param string $connectorName the name of the connector to use
	 * @param array $params a map of options for this method, as explained above
	 */
	public function fillSecondaryIndex($userId, $home, $connectorName, $params = []) {
		$minId = 0;
		$chunkSize = $params['chunkSize'] ?? 100;
		$startOver = $params['startOver'] ?? false;
		$callback = $params['callback'] ?? null;
		$adlerUser = \hash('adler32', $userId); // hash the user to ensure it fits in the config key

		$connector = $this->hub->getRegisteredConnector($connectorName);
		if ($connector === null) {
			return false;
		}

		if (!$startOver) {
			$minId = (int)$this->config->getValue("es_fillsec_{$connectorName}_{$adlerUser}", '0');
		}

		$fileIds = $this->mapper->findFilesIndexed($home, $chunkSize, $minId);
		while (!empty($fileIds)) {
			foreach ($fileIds as $fileId) {
				$fileStatus = $this->mapper->getOrCreateFromFileId($fileId);
				try {
					$nodes = $home->getById($fileId, true);
					if (isset($nodes[0])) {
						// if the node is successfully indexed, it should be already
						// marked as indexed, so nothing to do in that case
						if (!$connector->indexNode($userId, $nodes[0])) {
							// even if primary index is successful, mark it as error
							// so it can be retried in the secondary index
							$this->mapper->markError($fileStatus, 'Index failed');
						}
					} else {
						$this->logger->debug("fillSecondaryIndex: ($fileId) missing node", ['app' => 'search_elastic']);
						$fileStatus->setMessage('File vanished');
						$this->mapper->markVanished($fileStatus);
					}
				} catch (NotFoundException $e) {
					// mark the fileid as vanished in order to remove it later
					$this->logger->debug("fillSecondaryIndex: ($fileId) not found", ['app' => 'search_elastic']);
					$fileStatus->setMessage('File vanished');
					$this->mapper->markVanished($fileStatus);
				}
				$minId = $fileId;  // update minId
			}

			$this->config->setValue("es_fillsec_{$connectorName}_{$adlerUser}", $minId); // mark minId so we can start from there
			if ($callback !== null && \is_callable($callback)) {
				$callback($fileIds);
			}

			$fileIds = $this->mapper->findFilesIndexed($home, $chunkSize, $minId);
		}
		$this->config->deleteValue("es_fillsec_{$connectorName}_{$adlerUser}");
	}

	public function getConnectorInfo() {
		$registered = $this->hub->getRegisteredConnectorNames();
		$write = $this->config->getConfiguredWriteConnectors();
		$search = $this->config->getConfiguredSearchConnector();

		return [
			'registered' => $registered,
			'write' => $write,
			'search' => $search,
		];
	}
}
