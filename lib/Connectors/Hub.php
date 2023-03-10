<?php
/**
 * @copyright Copyright (c) 2023, ownCloud GmbH
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

namespace OCA\Search_Elastic\Connectors;

use OCP\IConfig;
use OCP\ILogger;
use OCP\Files\Node;

class Hub {
	/** @var IConfig */
	private $config;
	/** @var array<string, IConnector> */
	private $registeredConnectors = [];
	/** @var array<string, bool> */
	private $connectorsChecked = [];

	/** @var array<IConnector> */
	private $writeConnectors;
	/** @var IConnector */
	private $searchConnector;

	public function __construct(IConfig $config, ILogger $logger) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Register the connector so it can be used later
	 * @param IConnector $connector the connectore to register
	 */
	public function registerConnector(IConnector $connector) {
		$this->registeredConnectors[$connector->getConnectorName()] = $connector;
	}

	/**
	 * Get a list with the names of the registered connectors. Note that
	 * some of them might not be in use
	 * @return array
	 */
	public function getRegisteredConnectorNames() {
		return \array_keys($this->registeredConnectors);
	}

	/**
	 * Get the registered connector by name. Note that it might be
	 * in use
	 * @param string $name the name of the connector
	 * @return IConnector|null the connector or null if it isn't registered
	 */
	public function getRegisteredConnector(string $name) {
		return $this->registeredConnectors[$name] ?? null;
	}

	private function getWriteConnectors() {
		// TODO: Fetching the data from the config.php file is temporary
		if (!isset($this->writeConnectors)) {
			$writeConnectors = [];

			$writeNames = $this->config->getSystemValue('es.write', ['Legacy']);
			foreach ($writeNames as $writeName) {
				$connector = $this->registeredConnectors[$writeName] ?? null;
				if ($connector === null) {
					$this->logger->warning("Connector {$writeName} is missing", ['app' => 'search_elastic']);
					continue;
				}
				$writeConnectors[] = $connector;
			}

			if (empty($writeConnectors)) {
				$this->logger->warning("No valid connector found. Falling back to Legacy", ['app' => 'search_elastic']);
				// If Legacy isn't registered somehow, it will crash.
				$writeConnectors[] = $this->registeredConnectors['Legacy'];
			}

			$this->writeConnectors = $writeConnectors;
		}
		return $this->writeConnectors;
	}

	private function getSearchConnector() {
		// TODO: Fetching the data from the config.php file is temporary
		if (!isset($this->searchConnector)) {
			$searchName = $this->config->getSystemValue('es.search', 'Legacy');
			$connector = $this->registeredConnectors[$searchName] ?? null;
			if ($connector === null) {
				$this->logger->warning("Connector {$searchName} is missing. Falling back to Legacy", ['app' => 'search_elastic']);
				$connector = $this->registeredConnectors['Legacy'];
			}
			$this->searchConnector = $connector;
		}
		return $this->searchConnector;
	}

	/**
	 * We cache whether the connector has been checked or not in the current
	 * request. This method will clear that cache so the check can be performed
	 * again.
	 */
	public function clearConnectorsCheckedCache() {
		$this->connectorsChecked = [];
	}

	/**
	 * Each of the "write" connectors will have their attached indexes
	 * prepared to be used. If the indexes are already setup, nothing
	 * will be done to the index, otherwise the index will be setup
	 * accordingly to the connector.
	 *
	 * The cached status of the connector will still take precedence
	 * even with the $force parameter. If you want to use the $force
	 * parameter, use `clearConnectorsCheckedCache` method first to
	 * clear the cache.
	 *
	 * Expected usage with the $force parameter:
	 * ```
	 * $hub->clearConnectorsCheckedCache();
	 * $hub->prepareWriteIndexes(true);
	 * $hub->prepareSearchIndex(true);
	 * .....
	 * ```
	 */
	public function prepareWriteIndexes($force = false) {
		$allConnectorsOk = true;

		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $connector) {
			$connectorName = $connector->getConnectorName();
			if (isset($this->connectorsChecked[$connectorName])) {
				$allConnectorsOk = $allConnectorsOk && $this->connectorsChecked[$connectorName];
				continue;
			}

			if ($force === true) {
				$connector->prepareIndex();
				$connectorState = $connector->isSetup();
			} else {
				$connectorState = $connector->isSetup();
				if ($connectorState === false) {
					$connector->prepareIndex();
					// recheck again
					$connectorState = $connector->isSetup();
				}
			}
			$this->connectorsChecked[$connectorName] = $connectorState;
			$allConnectorsOk = $allConnectorsOk && $this->connectorsChecked[$connectorName];
		}
		return $allConnectorsOk;
	}

	/**
	 * Use the search connector to prepare it attached index. If the
	 * index is already setup, nothing will be done, otherwise the
	 * index will be setup accordingly to the connector.
	 *
	 * The cached status of the connector will still take precedence
	 * even with the $force parameter. If you want to use the $force
	 * parameter, use `clearConnectorsCheckedCache` method first to
	 * clear the cache.
	 *
	 * Expected usage with the $force parameter:
	 * ```
	 * $hub->clearConnectorsCheckedCache();
	 * $hub->prepareWriteIndexes(true);
	 * $hub->prepareSearchIndex(true);
	 * .....
	 * ```
	 */
	public function prepareSearchIndex($force = false) {
		$connector = $this->getSearchConnector();
		$connectorName = $connector->getConnectorName();
		if (isset($this->connectorsChecked[$connectorName])) {
			return $this->connectorsChecked[$connectorName];
		}

		if ($force === true) {
			$connector->prepareIndex();
			$connectorState = $connector->isSetup();
		} else {
			$connectorState = $connector->isSetup();
			if ($connectorState === false) {
				$connector->prepareIndex();
				// recheck again
				$connectorState = $connector->isSetup();
			}
		}
		$this->connectorsChecked[$connectorName] = $connectorState;
		return $this->connectorsChecked[$connectorName];
	}

	public function hubIsSetup() {
		return $this->prepareWriteIndexes() && $this->prepareSearchIndex();
	}

	/**
	 * Index the target node using all the configured write connectors.
	 */
	public function hubIndexNode(string $userId, Node $node, bool $extractContent = true) {
		$result = true;
		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $connector) {
			$connectorName = $connector->getConnectorName();
			if (!isset($this->connectorsChecked[$connectorName])) {
				$this->prepareWriteIndexes();
			}

			if ($this->connectorsChecked[$connectorName] !== true) {
				continue;
			}

			$conResult = $connector->indexNode($userId, $node, $extractContent);
			$result = $result && $conResult;
		}
		return $conResult;
	}

	/**
	 * Fetch the result of the query using the search connector.
	 * This method will return an array with:
	 * - 'result' -> \Elastica\ResultSet the result coming from
	 * the connector
	 * - 'connector' -> IConnector the search connector so you
	 * can use the `findInResult` method
	 *
	 * Usage example:
	 * ```
	 * $fetchResult = $hub->hubFetchResults(....);
	 * $c = $fetchResult['connector'];
	 * $mtime = $c->findInResult($fetchResult['result'], 'mtime');
	 * ```
	 *
	 * Note that accessing directly to the resultSet is discoraged
	 * because the data could be indexed in a different way
	 */
	public function hubFetchResults(string $userId, string $query, int $limit, int $offset) {
		$connector = $this->getSearchConnector();
		$connectorName = $connector->getConnectorName();
		if (!isset($this->connectorsChecked[$connectorName])) {
			$this->prepareSearchIndex();
		}

		if ($this->connectorsChecked[$connectorName] !== true) {
			return false;
		}

		$resultSet = $connector->fetchResults($userId, $query, $limit, $offset);
		return [
			'resultSet' => $resultSet,
			'connector' => $connector,
		];
	}

	/**
	 * Delete the indexed document by ownCloud's fileid.
	 * The deletion will happen on all the configured write connectors
	 */
	public function hubDeleteByFileId($fileId) {
		$deleted = true;
		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $connector) {
			$deleted = $connector->deleteByFileId($fileId) && $deleted;
		}
		return $deleted;
	}

	/**
	 * Get the stats of the indexes attached to all the connectors configured
	 * in the hub.
	 * The search connector will be retrieved first.
	 */
	public function hubGetStats() {
		$stats = [];

		$searchConnector = $this->getSearchConnector();
		$stats[$searchConnector->getConnectorName()] = $searchConnector->getStats();

		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $writeConnector) {
			$writeConnectorName = $writeConnector->getConnectorName();
			if (!isset($stats[$writeConnectorName])) {
				$stats[$writeConnectorName] = $writeConnector->getStats();
			}
		}
		return $stats;
	}
}
