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

use Elastica\Client;
use Elastica\Index;
use Elastica\Request;
use Elastica\Result;
use Elastica\ResultSet;
use OC\Files\Cache\Cache;
use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\Connectors\ElasticaFactory;
use OC\Share\Constants;
use OCP\Files\Node;
use OCP\Files\Folder;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

/**
 * Abstract class providing a common behavior to implement the IConnector
 * interface. Most of the public methods of the interface are marked as final
 * because we don't want subclasses of change the behavior. Subclasses can
 * and should override some of the protected methods. The protected methods
 * are expected to provide data or information that the subclasses can
 * customize according to their needs.
 *
 * Protected non-final method are expected to be overwritten by the subclasses
 * if the default code isn't suitable for them. Same for the public non-final
 * methods (except for the `isSetup` method, which shouldn't need to be changed)
 *
 * Note that this class is intended to be used as helper since subclasses are
 * only expected to provide information. If the behavior isn't suited for
 * your purposes, consider to implement the `IConnector` interface yourself.
 */
abstract class BaseConnector implements IConnector {
	/** @var Client */
	private $client;
	/** @var ElasticaFactory */
	private $factory;
	/** @var SearchElasticConfigService */
	private $esConfig;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ILogger */
	private $logger;

	/** @var Index|null */
	private $index;

	/**
	 * Constructor of the class. Subclasses must call this constructor
	 */
	public function __construct(
		Client $client,
		ElasticaFactory $factory,
		SearchElasticConfigService $esConfig,
		IGroupManager $groupManager,
		IUserManager $userManager,
		ILogger $logger
	) {
		$this->client = $client;
		$this->factory = $factory;
		$this->esConfig = $esConfig;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	/**
	 * Get the Elastica\Index instance associated with the connector.
	 * This method mustn't be overwritten, but it can be used in the
	 * subclasses if needed.
	 */
	final protected function getIndex() {
		if (!isset($this->index)) {
			$this->index = $this->factory->getNewIndex($this->client, $this->getIndexName());
		}
		return $this->index;
	}

	/**
	 * Check whether the connector is properly setup. This usually involves
	 * checking if the index and the ingest pipeline exist.
	 *
	 * This method can be overwritten, although it shouldn't be needed. You
	 * might want to include additional checks depending on what has been
	 * configured.
	 */
	public function isSetup(): bool {
		$index = $this->getIndex();
		if (!$index->exists()) {
			return false;
		}

		$result = $this->client->request("_ingest/pipeline/{$this->getProcessorName()}", Request::GET);
		if ($result->getStatus() === 404) {
			return false;
		}

		return true;
	}

	/**
	 * Provide the index settings as a map containing all the options.
	 * This method can be overwritten by subclasses to adjust the
	 * configuration of the index if needed.
	 * A default map is already returned, so overwritting this method
	 * is optional.
	 *
	 * Assuming $conf stores the value returned by this method,
	 * The index will be created like
	 * ```
	 * $index->create(['settings' => $conf], true);
	 * ```
	 * @return array
	 */
	protected function getIndexSettingsConf(): array {
		return [
			'number_of_shards' => 1,
			'number_of_replicas' => 0,
		];
	}

	/**
	 * Configure the mapping properties for each field in the index.
	 * An empty array can be returned if no mapping will be explictly
	 * set and you want to rely on the default one provided by
	 * elasticsearch. This is the default behavior.
	 * Subclass can overwrite this method if needed to configure the
	 * mapping as they want.
	 *
	 * Assuming $conf stores the value returned by this method,
	 * The mapping will be created like
	 * ```
	 * $mapping->setProperties($conf);
	 * ```
	 * @return array
	 */
	protected function getMappingPropertiesConf(): array {
		return [];
	}

	/**
	 * Return the payload needed to configure the processor pipeline.
	 * This includes the description and the processor list to be
	 * configured.
	 * An empty array can be returned if no processor pipeline is needed.
	 * A default configuration is already provided, but subclasses can
	 * overwrite the method if needed.
	 *
	 * The processor name will be the one returned by the `getProcessorName`
	 * method
	 * @return array
	 */
	protected function getProcessorConf(): array {
		$processors = [
			[
				'attachment' => [
					'field' => 'data',
					'target_field' => 'file',
					'indexed_chars' => '-1',
				]
			],
			[
				'remove' => [
					'field' => 'data',
				]
			],
		];
		$description = "Pipeline to process entries for ownCloud search with connector {$this->getConnectorName()}";

		$payload = [];
		$payload['description'] = $description;
		$payload['processors'] = $processors;
		return $payload;
	}

	/**
	 * @inheritDoc
	 */
	final public function prepareIndex() {
		$settings = $this->getIndexSettingsConf();

		$index = $this->getIndex();
		$index->create(['settings' => $settings], true);

		$mappingData = $this->getMappingPropertiesConf();
		if (!empty($mappingData)) {
			$mapping = $this->factory->getNewMapping();
			$mapping->setProperties($mappingData);
			$mapping->send($index);
		}

		$processorPayload = $this->getProcessorConf();
		if (!empty($processorPayload)) {
			$this->client->request("_ingest/pipeline/{$this->getProcessorName()}", Request::PUT, $processorPayload);
		}
	}

	/**
	 * Extract the data from the node that needs to be indexed. The $access
	 * array contains "users" and "groups" keys to know who have access
	 * to the node in order to index those if needed.
	 *
	 * Note that the node's content shouldn't be extracted here because it
	 * will be handled separately. We don't want to get the contents twice.
	 * In addition, the document id will always be the node id. There is no
	 * need to return it.
	 *
	 * The mtime is currently the only attribute that is required by ownCloud,
	 * so that must be index.
	 *
	 * A default implementation is already provided, but you can overwrite it
	 * to adjust the data to index.
	 * @return array
	 */
	protected function extractNodeData(Node $node, array $access): array {
		return [
			'name' => $node->getName(),
			'size' => $node->getSize(),
			'mtime' => $node->getMTime(),
			'users' => $access['users'],
			'groups' => $access['groups'],
		];
	}

	/**
	 * @inheritDoc
	 */
	final public function indexNode(string $userId, Node $node, bool $extractContent = true): bool {
		$this->logger->debug(
			"indexNode {$node->getPath()} ({$node->getId()}) for $userId",
			['app' => 'search_elastic']
		);

		$access = $this->getUsersWithReadPermission($node, $userId);
		$extractedData = $this->extractNodeData($node, $access);

		$doc = $this->factory->getNewDocument((string)$node->getId());
		foreach ($extractedData as $key => $value) {
			$doc->set($key, $value);
		}
		$doc->setDocAsUpsert(true);

		$index = $this->getIndex();
		if ($extractContent && $this->canExtractContent($node)) {
			$this->logger->debug(
				"indexNode: inserting document with pipeline processor: ".
				\json_encode($doc->getData()),
				['app' => 'search_elastic']
			);

			// @phan-suppress-next-line PhanUndeclaredMethod
			$doc->addFileContent('data', $node->getContent());

			// this is a workaround to acutally be able to use parameters when setting a document
			// see: https://github.com/ruflin/Elastica/issues/1248
			$bulk = $this->factory->getNewBulk($index->getClient());
			$bulk->setIndex($index);
			$bulk->setRequestParam('pipeline', $this->getProcessorName());
			$bulk->addDocuments([$doc]);
			$responseSet = $bulk->send();

			$result = $responseSet->isOk();
			if (!$result) {
				// we'll log only the first error found in the response set.
				$this->logger->error(
					"indexNode: connector {$this->getConnectorName()} failed: {$responseSet->getError()}",
					['app' => 'search_elastic']
				);
			}
			return $result;
		}

		$this->logger->debug(
			"indexNode: upserting document to index: ".
			\json_encode($doc->getData()),
			['app' => 'search_elastic']
		);
		$response = $index->updateDocument($doc);

		$result = $response->isOk();
		if (!$result) {
			$this->logger->error(
				"indexNode: connector {$this->getConnectorName()} failed: {$response->getError()}",
				['app' => 'search_elastic']
			);
		}
		return $result;
	}

	/**
	 * Get the elasticsearch query to be sent, as array
	 * The opts will include:
	 * - 'access' => containing the users and groups that are searching
	 *   + 'users' => users requesting the search (should be only one)
	 *   + 'groups' => groups requesting the search (usually the groups the user is member of)
	 * - 'searchContent' => whether it should search the content.
	 * - 'size' => size (number) of the results
	 * - 'from' => result offset
	 *
	 * A default query is provide trying to match the name of the file
	 * or its contents (if 'searchContent' has been requested), also checking
	 * if the users and groups should have access to the file.
	 *
	 * This method should be overwritten in order to adjust the behavior
	 * @return array
	 */
	protected function getElasticSearchQuery(string $query, array $opts): array {
		$users = \implode(' OR ', $opts['access']['users']);
		$groups = \implode(' OR ', $opts['access']['groups']);
		$size = $opts['size'] ?? 30;
		$from = $opts['from'] ?? 0;

		$es_query = [
			'query' => [
				'bool' =>  [
					'filter' => [
						[
							'bool' => [
								'should' => [
									[
										'match' => [
											'users' => $users,
										]
									],
									[
										'match' => [
											'groups' => $groups,
										],
									],
								],
							],
						],
					],
					'should' => [
						[
							'query_string' => [
								'query' =>  $query,
								'fields' => ['name'],
							],
						],
					],
					'minimum_should_match' => 1,
				],
			],
			'highlight' => [
				'fields' => ['file.content' => new \stdClass]
			],
			'_source' => [
				'includes' => ['mtime']
			],
			'size' => $size,
			'from' => $from,
		];

		if ($opts['searchContent']) {
			$es_query['query']['bool']['should'][] = [
				'query_string' => [
					'query' => $query,
					'fields' => ['file.content'],
				],
			];
		}
		return $es_query;
	}

	/**
	 * @inheritDoc
	 */
	final public function fetchResults(string $userId, string $query, int $limit, int $offset): ResultSet {
		$noContentGroups = $this->esConfig->getGroupNoContentArray();
		$searchContent = true;
		if (!$this->esConfig->shouldContentBeIncluded()) {
			$searchContent = false;
		}

		$userObj = $this->userManager->get($userId, true); // we get a DeletedUser instance of the user is missing

		$groups = $this->groupManager->getUserGroups($userObj);
		$groupIds = [];
		foreach ($groups as $group) {
			$groupId = $group->getGID();
			$groupIds[] = $groupId;
			if (\in_array($groupId, $noContentGroups)) {
				$searchContent = false;
			}
		}

		$opts = [
			'access' => [
				'users' => [$userId],
				'groups' => $groupIds,
			],
			'searchContent' => $searchContent,
			'size' => $limit,
			'from' => $offset,
		];
		$proposedEsQuery = $this->getElasticSearchQuery($query, $opts);

		$this->logger->info('query: ' . json_encode($proposedEsQuery), ['search_elastic']); // TODO: Reduce log level to debug
		$es_query = $this->factory->getNewQuery();
		$es_query->setRawQuery($proposedEsQuery);

		$search = $this->factory->getNewSearch($this->client);
		$search->addIndex($this->getIndex());
		return $search->search($es_query);
	}

	/**
	 * Find the key in the result. This method provides a default behavior
	 * assuming the resultSet is similar to the one returned by default
	 * from the fetchResults method.
	 * This means that the 'id' is the document's id, the highlights will be
	 * the file.content's highlights (if any) and any other key will be found
	 * in the result's data.
	 *
	 * This method can be overwritten in case the data is indexed
	 * differently. For example, if the key is 'mtime' but it has been
	 * indexed as 'modTime'
	 *
	 * Minimum expected keys are:
	 * - 'id' -> for the ownCloud's fileid'
	 * - 'highlights' -> for the file contents' highlights, if any
	 * - 'mtime' -> for the modification time of the file.
	 */
	public function findInResult(Result $result, string $key) {
		switch ($key) {
			case 'id':
				return (int)$result->getId();
			case 'highlights':
				$highlights = $result->getHighlights();
				return $highlights['file.content'] ?? [];
			default:
				$data = $result->getData();
				return $data[$key] ?? null;
		}
	}

	/**
	 * Regardless of the indexed fields, the base connector will
	 * always set the document's id to the fileid, so there is no need
	 * for subclasses to overwrite this method.
	 */
	final public function deleteByFileId($fileId): bool {
		$index = $this->getIndex();
		$response = $index->deleteById($fileId);
		// 404 is considered ok because the file id isn't in the index any longer
		return $response->isOk() || $response->getStatus() === 404;
	}

	/**
	 * Get the index stats as reported by elasticsearch
	 */
	final public function getStats(): array {
		$index = $this->getIndex();
		$stats = $index->getStats()->getData();
		return $stats;
	}

	/**
	 * @inheritDoc
	 *
	 * This is the public name that the admin should use to refer
	 * to this connector
	 * The connector's name must be under 30 chars
	 */
	abstract public function getConnectorName(): string;

	/**
	 * This is the name that we'll use to build the index and processor's name.
	 * Usually, for convenience, it should match the "public" connector name,
	 * but there are cases where the name could differ.
	 * Note that a prefix will be automatically added in order to prevent
	 * possible collisions with other ownCloud servers
	 * @return string
	 */
	abstract protected function getPrivateConnectorName(): string;

	/**
	 * The name of the index to connect to. This should be something like
	 * "oc-<oc_instanceid><private_connector_name>", such as "oc-oci0of1jdsfo-es_v33"
	 * (use "-es_v33" as privateConnectorName).
	 * This method will use the recommended prefix provided by the
	 * SearchElasticConfigService, which will use the ownCloud's instance id,
	 * so it should provide some kind of isolation in case there are multiple
	 * different ownCloud instances using the same elasticsearch server.
	 * The index name can't be overwritten by subclasses, but it can be slightly
	 * adjusted by using the `getPrivateConnectorName` method
	 * @return string the name of the index
	 */
	final protected function getIndexName() {
		return $this->esConfig->getRecommendedPrefixFor('index') . $this->getPrivateConnectorName();
	}

	/**
	 * The name of the processor to be created and used. The name will be
	 * derived from the `getPrivateConnectorName` and it will be something
	 * like "oc-processor-<oc_instanceid><private_connector_name>", such as
	 * "oc-processor-oci0of1jdsfo-es_v33" (use "-es_v33" as privateConnectorName).
	 * This method will use the recommended prefix provided by the
	 * SearchElasticConfigService, which will use the ownCloud's instance id,
	 * so it should provide some kind of isolation in case there are multiple
	 * different ownCloud instances using the same elasticsearch server.
	 * This method can me overwritten by subclasses, but it can be slightly
	 * adjusted by using the `getPrivateConnectorName` method
	 */
	final protected function getProcessorName() {
		return $this->esConfig->getRecommendedPrefixFor('processor') . $this->getPrivateConnectorName();
	}

	/**
	 * @param Node $node
	 * @param string $owner
	 * @return array
	 */
	private function getUsersWithReadPermission(Node $node, $owner) {
		// get path for lookup in sharing
		$path = $node->getPath();
		//TODO test this hack with subdirs and other storages like objectstore and files_external
		//if ($file->getStorage()->instanceOfStorage('\OC\Files\Storage\Home') && substr($path, 0, 6) === 'files/') {
		//	$path = substr($path, 6);
		//}
		$path = \substr($path, \strlen('/' . $owner . '/files'));
		return $this->getUsersSharingFile($path, $owner);
	}

	/**
	 * Find which users can access a shared item
	 * @param string $path to the file
	 * @param string $ownerUser owner of the file
	 * @return array
	 * @note $path needs to be relative to user data dir, e.g. 'file.txt'
	 *       not '/admin/data/file.txt'
	 */
	private function getUsersSharingFile($path, $ownerUser) {
		$this->logger->debug(
			"determining access to $path",
			['app' => 'search_elastic']
		);

		Filesystem::initMountPoints($ownerUser);
		$users = $groups = $sharePaths = $fileTargets = [];
//		$publicShare = false;
//		$remoteShare = false;
		$source = -1;
		$cache = false;

		$view = new View('/' . $ownerUser . '/files');
		$meta = $view->getFileInfo($path);
		if ($meta === false) {
			// if the file doesn't exists yet we start with the parent folder
			$meta = $view->getFileInfo(\dirname($path));
		}

		if ($meta !== false) {
			$source = $meta['fileid'];
			$cache = new Cache($meta['storage']);
		}

		while ($source !== -1) {
			// Fetch all shares with another user
			$query = \OC_DB::prepare(
				'SELECT `share_with`, `file_source`, `file_target`
				FROM
				`*PREFIX*share`
				WHERE
				`item_source` = ? AND `share_type` = ? AND `item_type` IN (\'file\', \'folder\')'
			);
			$result = $query->execute([$source, Constants::SHARE_TYPE_USER]);

			if ($result === false) {
				$this->logger->error(
					\OC_DB::getErrorMessage(),
					['app' => 'search_elastic']
				);
			} else {
				while ($row = $result->fetchRow()) {
					$users[] = $row['share_with'];
				}
			}

			// We also need to take group shares into account
			$query = \OC_DB::prepare(
				'SELECT `share_with`, `file_source`, `file_target`
				FROM
				`*PREFIX*share`
				WHERE
				`item_source` = ? AND `share_type` = ? AND `item_type` IN (\'file\', \'folder\')'
			);

			$result = $query->execute([$source, Constants::SHARE_TYPE_GROUP]);

			if ($result === false) {
				$this->logger->error(
					\OC_DB::getErrorMessage(),
					['app' => 'search_elastic']
				);
			} else {
				while ($row = $result->fetchRow()) {
					$groups[] = $row['share_with'];
				}
			}

			// let's get the parent for the next round
			$meta = $cache->get((int)$source);
			if ($meta !== false) {
				// Cache->get() returns ICacheEntry which doesnot have array access.
				// @phan-suppress-next-line PhanTypeArraySuspicious
				$source = (int)$meta['parent'];
			} else {
				$source = -1;
			}
		}

		// Include owner in list of users
		$users[] = $ownerUser;

		$result = ['users' => \array_values(\array_unique($users)), 'groups' => \array_values(\array_unique($groups))];
		$this->logger->debug(
			"access to $path:" . \json_encode($result),
			['app' => 'search_elastic']
		);
		return $result;
	}

	private function canExtractContent(Node $node) {
		$storage = $node->getStorage();
		$size = $node->getSize();
		$maxSize = $this->esConfig->getMaxFileSizeForIndex();

		$extractContent = true;
		if (!$this->esConfig->shouldContentBeIncluded()) {
			$this->logger->debug(
				"indexNode: content should not be included, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($node instanceof Folder) {
			$this->logger->debug(
				"indexNode: folder, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size < 0) {
			$this->logger->debug(
				"indexNode: unknown size, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size === 0) {
			$this->logger->debug(
				"indexNode: file empty, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size > $maxSize) {
			$this->logger->debug(
				"indexNode: file exceeds $maxSize, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($this->esConfig->getScanExternalStorageFlag() === false
			&& $storage->isLocal() === false) {
			$this->logger->debug(
				"indexNode: not indexing on remote storage {$storage->getId()}, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		}
		return $extractContent;
	}
}
