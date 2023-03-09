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
	 * Each of the "write" connectors will have their attached indexes
	 * prepared to be used. If the indexes are already setup, nothing
	 * will be done to the index, otherwise the index will be setup
	 * accordingly to the connector.
	 */
	public function prepareWriteIndexes() {
		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $connector) {
			$connectorName = $connector->getConnectorName();
			if (isset($this->connectorsChecked[$connectorName])) {
				continue;
			}

			$connectorState = $connector->isSetup();
			if ($connectorState === false) {
				$connector->prepareIndex();
				// recheck again
				$connectorState = $connectorState->isSetup();
			}
			$this->connectorsChecked[$connectorName] = $connectorState;
		}
	}

	/**
	 * Use the search connector to prepare it attached index. If the
	 * index is already setup, nothing will be done, otherwise the
	 * index will be setup accordingly to the connector.
	 */
	public function prepareSearchIndex() {
		$connector = $this->getSearchConnector();
		$connectorName = $connector->getConnectorName();
		if (isset($this->connectorsChecked[$connectorName])) {
			return;
		}

		$connectorState = $connector->isSetup();
		if ($connectorState === false) {
			$connector->prepareIndex();
			// recheck again
			$connectorState = $connectorState->isSetup();
		}
		$this->connectorsChecked[$connectorName] = $connectorState;
	}

	/**
	 * Index the target node using all the configured write connectors.
	 */
	public function hubIndexNode(string $userId, Node $node, bool $extractContent = true) {
		$writeConnectors = $this->getWriteConnectors();
		foreach ($writeConnectors as $connector) {
			$connectorName = $connector->getConnectorName();
			if (!isset($this->connectorsChecked[$connectorName])) {
				$this->prepareWriteIndexes();
			}

			if ($this->connectorsChecked[$connectorName] !== true) {
				continue;
			}

			$connector->indexNode($userId, $node, $extractContent);
		}
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
}
