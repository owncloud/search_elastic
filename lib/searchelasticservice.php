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

use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
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
use OCP\ILogger;
use OCP\IConfig;
use OCP\IServerContainer;

class SearchElasticService {

	const PROCESSOR_NAME = 'oc-processor';

	/**
	 * @var IServerContainer
	 */
	private $server;

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
	 * SearchElasticService constructor.
	 *
	 * @param IServerContainer $server
	 * @param Index $index
	 * @param StatusMapper $mapper
	 * @param ILogger $logger
	 * @param SearchElasticConfigService $config
	 */
	public function __construct(
		IServerContainer $server,
		Index $index,
		StatusMapper $mapper,
		ILogger $logger,
		SearchElasticConfigService $config
	) {
		$this->server = $server;
		$this->mapper = $mapper;
		$this->logger = $logger;
		$this->config = $config;
		$this->index = $index;
		$this->client = $this->index->getClient();

		$this->type = new Type($this->index, 'file');

	}

	/**
	 * sets up index, processor and clears mapping
	 */
	public function setup() {
		$this->setpIndex();
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
		$result = $this->client->request("_ingest/pipeline/".self::PROCESSOR_NAME, Request::GET);
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
					'ignore_missing'=> true
				]
			],
			[
				'remove' => [
					'field'			=> 'data',
					'ignore_failure'=> true
				]
			],
			[
				'rename' => [
					'field'			=> 'mtime',
					'target_field'	=> 'file.mtime',
					'ignore_missing'=> true
				]
			],
			[
				'rename' => [
					'field'			=> 'name',
					'target_field'	=> 'file.name',
					'ignore_missing'=> true
				]
			],
			[
				'rename' => [
					'field'			=> 'users',
					'target_field'	=> 'file.users',
					'ignore_missing'=> true
				]
			],
			[
				'rename' => [
					'field'			=> 'size',
					'target_field'	=> 'file.size',
					'ignore_missing'=> true
				]
			],
			[
				'rename' => [
					'field'			=> 'groups',
					'target_field'	=> 'file.groups',
					'ignore_missing'=> true
				]
			]
		];

		$payload = [];
		$payload['description'] = 'Pipeline to process Entries for Owncloud Search';
		$payload['processors'] = $processors;


		$response = $this->client->request("_ingest/pipeline/".self::PROCESSOR_NAME, Request::PUT, $payload);
		//TODO: verify that we setup the processor correctly

	}

	/**
	 * WARNING: will delete the index if it exists
	 */
	private function setpIndex() {
		// the number of shards and replicas should be adjusted as necessary outside of owncloud
		$this->index->create(array('index' => array('number_of_shards' => 1, 'number_of_replicas' => 0),), true);

		$type = new Type($this->index, 'file');

		$mapping = new Type\Mapping($type, array(
			// indexed for all files and folders
			'size'           => [ 'type' => 'long',   'store' => true ],
			'name'           => [ 'type' => 'text', 'store' => true ],
			'mtime'          => [ 'type' => 'long',   'store' => true ],
			'users'          => [ 'type' => 'text', 'store' => true ],
			'groups'         => [ 'type' => 'text', 'store' => true ],
			// only indexed when content was extracted
			'content' => [
				'type' => 'text', 'store' => true,
				'term_vector' => 'with_positions_offsets',
			],
			'title' => [
				'type' => 'text', 'store' => true,
				'term_vector' => 'with_positions_offsets',
			],
			'date'           => [ 'type' => 'text', 'store' => true ],
			'author'         => [ 'type' => 'text', 'store' => true ],
			'keywords'       => [ 'type' => 'text', 'store' => true ],
			'content_type'   => [ 'type' => 'text', 'store' => true ],
			'content_length' => [ 'type' => 'long',   'store' => true ],
			'language'       => [ 'type' => 'text', 'store' => true ],
		));
		$type->setMapping($mapping);
	}

	// === CONTENT CHANGES / FULL INDEXING ====================================
	/**
	 * @param string $userId
	 * @param int[] $fileIds
	 * @param bool $extractContent
	 */
	public function indexNodes ($userId, array $fileIds, $extractContent = true) {

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
					if (strpos($path, '/' . $skippedDir . '/') !== false //contains dir
						|| strrpos($path, '/' . $skippedDir) === strlen($path) - (strlen($skippedDir) + 1) // ends with dir
					) {
						throw new SkippedException("dir $path ($id) matches filter '$skippedDir'");
					}
				}

				if ($this->indexNode($userId, $node, $extractContent)) {
					$this->mapper->markIndexed($fileStatus);
				}

			} catch (VanishedException $e) {

				$this->logger->debug( "indexFiles: ($id) Vanished", ['app' => 'search_elastic'] );
				$fileStatus->setMessage('File vanished');
				$this->mapper->markVanished($fileStatus);

			} catch (NotIndexedException $e) {

				$this->logger->debug( "indexFiles: $path ($id) Not indexed", ['app' => 'search_elastic'] );
				$fileStatus->setMessage('Not indexed');
				$this->mapper->markUnIndexed($fileStatus);

			} catch (SkippedException $e) {

				$this->logger->debug( "indexFiles: $path ($id) Skipped", ['app' => 'search_elastic'] );
				$this->logger->debug( $e->getMessage(), ['app' => 'search_elastic']);
				$this->mapper->markSkipped($fileStatus, 'Skipped');

			} catch (\Exception $e) {
				//sqlite might report database locked errors when stock filescan is in progress
				//this also catches db locked exception that might come up when using sqlite
				$this->logger->logException($e, ['app' => 'search_elastic']);

				$this->mapper->markError($fileStatus, substr($e->getMessage(), 0, 255));
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

		$data = ['size' => $size = $node->getSize()];

		// we do not index the path because it might be different for each user
		// FIXME what about shared files? the recipient can rename them ...
		$data['name'] = $node->getName();

		$data['mtime'] = $node->getMTime();

		$access = $this->getUsersWithReadPermission($node, $userId);
		$data['users'] = $access['users'];
		$data['groups'] = $access['groups'];

		if ($this->canExtractContent($node, $extractContent)) {
			$data['data'] = base64_encode($node->getContent());
		}

		$doc = new Document($node->getId());
		$doc->setData($data);

		$this->logger->debug("indexNode: upserting document to index: ".
			json_encode($data), ['app' => 'search_elastic']
		);
		$doc->setDocAsUpsert(true);
		// this is a workaround to acutally be able to use parameters when setting a document
		// see: https://github.com/ruflin/Elastica/issues/1248
		$bulk = new Bulk($this->index->getClient());
		$bulk->setType($this->type);
		$bulk->setRequestParam('pipeline', self::PROCESSOR_NAME);
		$bulk->addDocuments([$doc]);
		$bulk->send();

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

		$noContent = $this->config->getIndexNoContentFlag();

		if ( $noContent === true || $noContent === 1
			|| $noContent === 'true' || $noContent === '1' || $noContent === 'on' ) {
			$this->logger->debug("indexNode: folder, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ( $node instanceof Folder ) {
			$this->logger->debug("indexNode: folder, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ($size < 0) {
			$this->logger->debug("indexNode: unknown size, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ($size === 0) {
			$this->logger->debug("indexNode: file empty, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ($size > $this->config->getMaxFileSizeForIndex()) {
			$this->logger->debug("indexNode: file exceeds $maxSize, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ($this->config->getScanExternalStorageFlag() === false
			&& $storage->isLocal() === false) {
			$this->logger->debug("indexNode: not indexing on remote storage {$storage->getId()}, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		}
		return $extractContent;
	}

	// === DELETE =============================================================

	public function deleteFiles (array $fileIds) {
		if (count($fileIds) > 0) {
			$result = $this->type->deleteIds($fileIds);
			$count = 0;
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
	function getNodeForId ($userId, $fileId) {

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
			throw new VanishedException($fileId);
		}

		if ( $node instanceof File || $node instanceof Folder ) {
			return $node;
		}
		throw new NotIndexedException();
	}


	public function getUsersWithReadPermission(Node $node, $owner) {
		// get path for lookup in sharing
		$path = $node->getPath();
		//TODO test this hack with subdirs and other storages like objectstore and files_external
		//if ($file->getStorage()->instanceOfStorage('\OC\Files\Storage\Home') && substr($path, 0, 6) === 'files/') {
		//	$path = substr($path, 6);
		//}
		$path = substr($path, strlen('/' . $owner . '/files'));
		return $this->getUsersSharingFile( $path, $owner );
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
			$meta = $view->getFileInfo(dirname($path));
		}

		if($meta !== false) {
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
			$result = $query->execute(array($source, Constants::SHARE_TYPE_USER));

			if (\OCP\DB::isError($result)) {
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

			$result = $query->execute(array($source, Constants::SHARE_TYPE_GROUP));

			if (\OCP\DB::isError($result)) {
				$this->logger->error(\OC_DB::getErrorMessage(),
					['app' => 'search_elastic']);
			} else {
				while ($row = $result->fetchRow()) {
					$groups[] = $row['share_with'];
				}
			}

			// let's get the parent for the next round
			$meta = $cache->get((int)$source);
			if($meta !== false) {
				$source = (int)$meta['parent'];
			} else {
				$source = -1;
			}
		}

		// Include owner in list of users
		$users[] = $ownerUser;

		$result = array('users' => array_unique($users), 'groups' => array_unique($groups));
		$this->logger->debug("access to $path:".json_encode($result),
			['app' => 'search_elastic']
		);
		return $result;
	}
} 