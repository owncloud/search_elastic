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

class Client {

	/**
	 * @var IServerContainer
	 */
	private $server;
	/**
	 * @var Index
	 */
	private $index;
	/**
	 * @var Index
	 */
	private $tempIndex;
	/**
	 * @var Type
	 */
	private $type;
	/**
	 * @var Type
	 */
	private $tempType;
	/**
	 * @var StatusMapper
	 */
	private $mapper;
	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @param IServerContainer $server
	 * @param Index $index
	 * @param Index $tempIndex used only to extract content
	 * @param StatusMapper $mapper
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(IServerContainer $server, Index $index, Index $tempIndex, StatusMapper $mapper, ILogger $logger, IConfig $config) {
		$this->server = $server;
		$this->mapper = $mapper;
		$this->logger = $logger;
		$this->config = $config;
		$this->index = $index;
		$this->tempIndex = $tempIndex;

		$this->type = new Type($this->index, 'file');
		$this->tempType = new Type($this->tempIndex, 'file');

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

				$skippedDirs = explode(';',	$this->config->getUserValue(
					$userId, 'search_elastic',
					'skipped_dirs', '.git;.svn;.CVS;.bzr'
				));

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

		$this->index->optimize();
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

		// index content for local files only
		$storage = $node->getStorage();

		$size = $node->getSize();
		$maxSize = $this->config->getAppValue('search_elastic', 'max_size', 10485760);

		// there are various reasons for not indexing the content
		$noContent = $this->config->getAppValue('search_elastic', 'nocontent', false);
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
		} else if ($size > $maxSize) {
			$this->logger->debug("indexNode: file exceeds $maxSize, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		} else if ($this->config->getAppValue('search_elastic', 'scanExternalStorages', true) === false
			&& $storage->isLocal() === false) {
			$this->logger->debug("indexNode: not indexing on remote storage {$storage->getId()}, skipping content extraction",
				['app' => 'search_elastic']
			);
			$extractContent = false;
		}

		if ($extractContent) {
			$data = $this->extractContent($node);
			if (empty($data)) {
				$this->logger->debug("indexNode: no content extracted for " .
					"{$node->getPath()} ({$node->getId()})",
					['app' => 'search_elastic']
				);
				$data = ['size' => $size];
			}
		} else {
			$data = ['size' => $size];
		}

		$data['name'] = $node->getName();
		// we do not index the path because it might be different for each user
		// FIXME what about shared files? the recipient can rename them ...
		$data['mtime'] = $node->getMTime();

		$access = $this->getUsersWithReadPermission($node, $userId);
		$data['users'] = $access['users'];
		$data['groups'] = $access['groups'];

		$doc = new Document($node->getId());
		$doc->set('file', $data);

		$this->logger->debug("indexNode: upserting document to index: ".
			json_encode($data), ['app' => 'search_elastic']
		);
		$doc->setDocAsUpsert(true);
		$this->type->updateDocument($doc);

		return true;

	}

	/**
	 * @param File $file
	 *
	 * @return array with file content as plain text and metadata
	 */
	public function extractContent (File $file) {

		$path = $file->getPath();

		$this->logger->debug("Extracting content for $path",
			['app' => 'search_elastic']
		);

		$doc = new Document($file->getId());

		$value = [
			'_content_type' => $file->getMimeType(),
			'_content' => base64_encode($file->getContent()),
		];

		$doc->set('file', $value);

		// index with elasticsearch (uses apache tika to extract the file content)
		$this->tempType->addDocument($doc);

		//wait a sec to allow the doc to become searchable
		sleep(1);
		// TODO use exists check

		// now get the file content
		$response = $this->tempType->request(urlencode($file->getId()).'?fields=file,file.content,file.title,file.date,file.author,file.keywords,file.content_type,file.content_length,file.language', Request::GET, array(), array());

		$data = $response->getData();
		$result = array();
		if (isset($data['fields'])) {
			if (isset($data['fields']['file.content'])) {
				$result['content'] = $data['fields']['file.content'][0];
			}
			if (isset($data['fields']['file.title'])) {
				$result['title'] = $data['fields']['file.title'][0];
			}
			if (isset($data['fields']['file.date'])) {
				$result['date'] = $data['fields']['file.date'][0];
			}
			if (isset($data['fields']['file.author'])) {
				$result['author'] = $data['fields']['file.author'][0];
			}
			if (isset($data['fields']['file.keywords'])) {
				$result['keywords'] = $data['fields']['file.keywords'][0];
			}
			if (isset($data['fields']['file.content_type'])) {
				$result['content_type'] = $data['fields']['file.content_type'][0];
			}
			if (isset($data['fields']['file.content_length'])) {
				$result['content_length'] = $data['fields']['file.content_length'][0];
			}
			if (isset($data['fields']['file.language'])) {
				$result['language'] = $data['fields']['file.language'][0];
			}
		}
		$this->logger->debug("$path content is:".json_encode($result),
			['app' => 'search_elastic']
		);

		$this->tempType->deleteById($file->getId());
		return $result;

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