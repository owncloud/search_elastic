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

namespace OCA\Search_Elastic;

use Elastica\Client;
use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use Elastica\Search;
use Elastica\Type;
use Elastica\Document;
use Elastica\Bulk;
use OC\Files\Cache\Cache;
use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Search_Elastic\Db\StatusMapper;
use OC\Share\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\ILogger;

class SearchElasticService {
	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var Type
	 */
	private $type;

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
	 * @var \Elastica\Client
	 */
	private $client;

	/**
	 * @var string
	 */
	private $processorName;

	/**
	 * searchelasticservice Constructor
	 *
	 * @param IConfig $serverConfig
	 * @param StatusMapper $mapper
	 * @param ILogger $logger
	 * @param Client $client
	 * @param SearchElasticConfigService $config
	 */
	public function __construct(
		IConfig $serverConfig,
		StatusMapper $mapper,
		ILogger $logger,
		Client $client,
		SearchElasticConfigService $config
	) {
		$this->mapper = $mapper;
		$this->logger = $logger;
		$this->config = $config;
		$this->client = $client;

		$instanceID = $serverConfig->getSystemValue('instanceid', '');
		$this->index = new Index($client, 'oc-' . $instanceID);
		$this->type = new Type($this->index, 'file');
		$this->processorName = 'oc-processor-' . $instanceID;
	}

	/**
	 * sets up index, processor and clears mapping
	 */
	public function setup() {
		$this->setupIndex();
		$this->setupProcessor();
		$this->mapper->clear();
	}

	/**
	 * @return bool
	 */
	public function isSetup() {
		return $this->isIndexSetup()
			&& $this->isProcessorSetup();
	}

	/**
	 * @return bool
	 */
	public function isIndexSetup() {
		return $this->index->exists();
	}

	/**
	 * @return bool
	 */
	public function isProcessorSetup() {
		$result = $this->client->request("_ingest/pipeline/".$this->processorName, Request::GET);
		if ($result->getStatus() === 404) {
			return false;
		}
		return true;
	}

	/**
	 * @return array
	 */
	public function getStats() {
		$stats = $this->index->getStats()->getData();
		$indexName = $this->index->getName();
		$countIndexed = $this->mapper->countIndexed();
		return [
			'_all'     => $stats['_all'],
			'_shards'  => $stats['_shards'],
			'oc_index' => $stats['indices'][$indexName],
			'countIndexed'  => $countIndexed,
		];
	}

	/**
	 * setup the processor pipeline for ingest-attachment processing
	 * Note: creating the array manually is necessary, since Elastica < 6
	 * does not have pipeline/ingest support
	 */
	private function setupProcessor() {
		$processors = [
			[
				'attachment' => [
					'field' 		=> 'data',
					'target_field' 	=> 'file',
					'indexed_chars'	=> '-1',
				]
			],
			[
				'remove' => [
					'field'			=> 'data',
				]
			],
		];

		$payload = [];
		$payload['description'] = 'Pipeline to process Entries for Owncloud Search';
		$payload['processors'] = $processors;

		$this->client->request("_ingest/pipeline/".$this->processorName, Request::PUT, $payload);
	}

	/**
	 * WARNING: will delete the index if it exists
	 */
	private function setupIndex() {
		// the number of shards and replicas should be adjusted as necessary outside of owncloud
		$this->index->create(['index' => ['number_of_shards' => 1, 'number_of_replicas' => 0],], true);

		$type = new Type($this->index, 'file');

		$mapping = new Type\Mapping($type, [
			// indexed for all files and folders
			'size'           => [ 'type' => 'long',   'store' => true ],
			'name'           => [ 'type' => 'text', 'store' => true ],
			'mtime'          => [ 'type' => 'long',   'store' => true ],
			'users'          => [ 'type' => 'text', 'store' => true ],
			'groups'         => [ 'type' => 'text', 'store' => true ],
			// only indexed when content was extracted
			'file.content' => [
				'type' => 'text', 'store' => true,
				'term_vector' => 'with_positions_offsets',
			],
			'file.title' => [
				'type' => 'text', 'store' => true,
				'term_vector' => 'with_positions_offsets',
			],
			'file.date'           => [ 'type' => 'text', 'store' => true ],
			'file.author'         => [ 'type' => 'text', 'store' => true ],
			'file.keywords'       => [ 'type' => 'text', 'store' => true ],
			'file.content_type'   => [ 'type' => 'text', 'store' => true ],
			'file.content_length' => [ 'type' => 'long',   'store' => true ],
			'file.language'       => [ 'type' => 'text', 'store' => true ],
		]);
		$type->setMapping($mapping);
	}

	/**
	 * @param \Elastica\Query $es_query
	 * @return \Elastica\ResultSet
	 */
	public function search($es_query) {
		$search = new Search($this->client);
		$search->addType($this->type);
		$search->addIndex($this->index);
		return $search->search($es_query);
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

				if ($this->indexNode($userId, $node, $extractContent)) {
					$this->mapper->markIndexed($fileStatus);
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

		$this->index->forcemerge();
	}

	/**
	 * index a file
	 *
	 * @param string $userId
	 * @param Node $node the file or folder to be indexed
	 * @param bool $extractContent
	 *
	 * @return bool true when something was stored in the index, false otherwise (eg, folders are not indexed)
	 * @throws NotIndexedException when an unsupported file type is encountered
	 */
	public function indexNode($userId, Node $node, $extractContent = true) {
		$this->logger->debug("indexNode {$node->getPath()} ({$node->getId()}) for $userId",
			['app' => 'search_elastic']
		);

		$doc = new Document($node->getId());

		// we do not index the path because it might be different for each user
		// FIXME what about shared files? the recipient can rename them ...
		$doc->set('name', $node->getName());

		$doc->set('size', $node->getSize());
		$doc->set('mtime', $node->getMTime());

		// document permissions
		$access = $this->getUsersWithReadPermission($node, $userId);
		$doc->set('users', $access['users']);
		$doc->set('groups', $access['groups']);

		$doc->setDocAsUpsert(true);

		if ($this->canExtractContent($node, $extractContent)) {
			$this->logger->debug(
				"indexNode: inserting document with pipeline processor: ".
				\json_encode($doc->getData()),
				['app' => 'search_elastic']
			);

			// @phan-suppress-next-line PhanUndeclaredMethod
			$doc->addFileContent('data', $node->getContent());

			// this is a workaround to acutally be able to use parameters when setting a document
			// see: https://github.com/ruflin/Elastica/issues/1248
			$bulk = new Bulk($this->index->getClient());
			$bulk->setType($this->type);
			$bulk->setRequestParam('pipeline', $this->processorName);
			$bulk->addDocuments([$doc]);
			$bulk->send();
			return true;
		}

		$this->logger->debug(
			"indexNode: upserting document to index: ".
			\json_encode($doc->getData()), ['app' => 'search_elastic']
		);
		$this->type->updateDocument($doc);
		return true;
	}

	/**
	 * Function that checks if we should also extract content
	 *
	 * @param Node $node
	 * @param bool $extractContent
	 * @return bool
	 */
	private function canExtractContent(Node $node, $extractContent = true) {
		$storage = $node->getStorage();
		$size = $node->getSize();
		$maxSize = $this->config->getMaxFileSizeForIndex();

		if (!$this->config->shouldContentBeIncluded()) {
			$this->logger->debug("indexNode: folder, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($node instanceof Folder) {
			$this->logger->debug("indexNode: folder, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size < 0) {
			$this->logger->debug("indexNode: unknown size, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size === 0) {
			$this->logger->debug("indexNode: file empty, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($size > $maxSize) {
			$this->logger->debug("indexNode: file exceeds $maxSize, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} elseif ($this->config->getScanExternalStorageFlag() === false
			&& $storage->isLocal() === false) {
			$this->logger->debug("indexNode: not indexing on remote storage {$storage->getId()}, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		}
		return $extractContent;
	}

	// === DELETE =============================================================
	/**
	 * @param array $fileIds
	 * @return int
	 */
	public function deleteFiles(array $fileIds) {
		if (\count($fileIds) > 0) {
			$result = $this->type->deleteIds($fileIds);
			$count = 0;

			// @phan-suppress-next-line PhanTypeNoAccessiblePropertiesForeach
			foreach ($result as $response) {
				/** @var Response $response */
				if ($response->isOk()) {
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
		$nodes = \OC::$server->getUserFolder($userId)->getById($fileId);
		// getById can return more than one id because the containing storage might be mounted more than once
		// Since we only want to index the file once, we only use the first entry

		if (isset($nodes[0])) {
			$this->logger->debug("getFileForId: $fileId -> node {$nodes[0]->getPath()} ({$nodes[0]->getId()})",
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
	 * @param Node $node
	 * @param string $owner
	 * @return array
	 */
	public function getUsersWithReadPermission(Node $node, $owner) {
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
	public function getUsersSharingFile($path, $ownerUser) {
		$this->logger->debug("determining access to $path",
			['app' => 'search_elastic']
		);

		Filesystem::initMountPoints($ownerUser);
		$users = $groups = $sharePaths = $fileTargets = [];
//		$publicShare = false;
//		$remoteShare = false;
		$source = -1;
		$cache = false;

		$view = new  View('/' . $ownerUser . '/files');
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
				$this->logger->error(\OC_DB::getErrorMessage(),
					['app' => 'search_elastic']);
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
				$this->logger->error(\OC_DB::getErrorMessage(),
					['app' => 'search_elastic']);
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

		$result = ['users' => \array_unique($users), 'groups' => \array_unique($groups)];
		$this->logger->debug(
			"access to $path:" . \json_encode($result),
			['app' => 'search_elastic']
		);
		return $result;
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
}
