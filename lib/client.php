<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic;

use Elastica\Exception\NotFoundException;
use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use Elastica\Type;
use Elastica\Document;
use OC\Files\Filesystem;
use OCA\Search_Elastic\Db\StatusMapper;
use OC\Share\Constants;
use OCP\Files\File;
use OCP\ILogger;
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
	 * @var array
	 */
	private $skippedDirs;
	/**
	 * @var StatusMapper
	 */
	private $mapper;
	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var bool
	 */
	private $scanExternalStorages;

	/**
	 * @param IServerContainer $server
	 * @param Index $index
	 * @param Index $tempIndex used only to extract content
	 * @param array $skippedDirs
	 * @param StatusMapper $mapper
	 * @param ILogger $logger
	 */
	public function __construct(IServerContainer $server, Index $index, Index $tempIndex, array $skippedDirs, StatusMapper $mapper, ILogger $logger, $scanExternalStorages) {
		$this->server = $server;
		$this->skippedDirs = $skippedDirs;
		$this->mapper = $mapper;
		$this->logger = $logger;
		$this->index = $index;
		$this->tempIndex = $tempIndex;
		$this->scanExternalStorages = $scanExternalStorages;

		$this->type = new Type($this->index, 'file');
		$this->tempType = new Type($this->tempIndex, 'file');

	}

	/**
	 * @param array $fileIds
	 * @param \OC_EventSource $eventSource
	 */
	public function indexFiles (array $fileIds, \OC_EventSource $eventSource = null) {

		foreach ($fileIds as $id) {

			$fileStatus = $this->mapper->getOrCreateFromFileId($id);

			try {
				// before we start mark the file as error so we know there
				// was a problem in case the php execution dies and we don't try
				// the file again
				$this->mapper->markError($fileStatus);

				$file = $this->getFileForId($id);

				$path = $file->getPath();

				foreach ($this->skippedDirs as $skippedDir) {
					if (strpos($path, '/' . $skippedDir . '/') !== false //contains dir
						|| strrpos($path, '/' . $skippedDir) === strlen($path) - (strlen($skippedDir) + 1) // ends with dir
					) {
						throw new SkippedException('skipping file '.$id.':'.$path);
					}
				}

				if ($eventSource) {
					$eventSource->send('indexing', $path);
				}

				if ($this->indexFile($file)) {
					$this->mapper->markIndexed($fileStatus);
				}

			} catch (VanishedException $e) {

				$fileStatus->setMessage('File vanished');
				$this->mapper->markVanished($fileStatus);

			} catch (NotIndexedException $e) {

				$fileStatus->setMessage('Not indexed');
				$this->mapper->markUnIndexed($fileStatus);

			} catch (SkippedException $e) {

				$this->mapper->markSkipped($fileStatus, 'Skipped');
				$this->logger->debug( $e->getMessage() );

			} catch (\Exception $e) {
				//sqlite might report database locked errors when stock filescan is in progress
				//this also catches db locked exception that might come up when using sqlite
				$this->logger->error($e->getMessage() . ' Trace:\n' . $e->getTraceAsString() );

				$this->mapper->markError($fileStatus, substr($e->getMessage(), 0, 255));
				// TODO Add UI to trigger rescan of files with status 'E'rror?
				if ($eventSource) {
					$eventSource->send('error', $e->getMessage());
				}
			}
		}

		$this->index->optimize();
	}

	/**
	 * @param $fileId
	 * @return File
	 * @throws NotIndexedException
	 * @throws VanishedException
	 */
	function getFileForId ($fileId) {

		/* @var Node[] */
		$nodes = $this->server->getUserFolder()->getById($fileId);
		// getById can return more than one id because the containing storage might be mounted more than once
		// Since we only want to index the file once, we only use the first entry

		if (isset($nodes[0])) {
			$node = $nodes[0];
		} else {
			throw new VanishedException($fileId);
		}

		if ( ! $node instanceof File ) {
			throw new NotIndexedException();
		}
		return $node;
	}

	/**
	 * index a file
	 *
	 * @param File $file the file to be indexed
	 *
	 * @return bool true when something was stored in the index, false otherwise (eg, folders are not indexed)
	 * @throws NotIndexedException when an unsupported file type is encountered
	 */
	public function indexFile(File $file) {

		// index content for local files only
		$storage = $file->getStorage();

		if ($this->scanExternalStorages || $storage->isLocal()) {
			$data = $this->extractContent($file);

			if (!empty($data)) {
				$access = $this->getUsersWithReadPermission($file);
				$data['users'] = $access['users'];
				$data['groups'] = $access['groups'];
				$data['mtime'] = $file->getMTime();

				$doc = new Document($file->getId());

				$doc->set('file', $data);
				$this->type->addDocument($doc);
			}

		}

		return true;

	}

	public function getUsersWithReadPermission(File $file) {
		$owner = $this->server->getUserSession()->getUser()->getUID();
		// get path for lookup in sharing
		$path = $file->getPath();
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

		Filesystem::initMountPoints($ownerUser);
		$users = $groups = $sharePaths = $fileTargets = [];
//		$publicShare = false;
//		$remoteShare = false;
		$source = -1;
		$cache = false;

		$view = new \OC\Files\View('/' . $ownerUser . '/files');
		$meta = $view->getFileInfo($path);
		if ($meta === false) {
			// if the file doesn't exists yet we start with the parent folder
			$meta = $view->getFileInfo(dirname($path));
		}

		if($meta !== false) {
			$source = $meta['fileid'];
			$cache = new \OC\Files\Cache\Cache($meta['storage']);
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
				\OCP\Util::writeLog('OCP\Share', \OC_DB::getErrorMessage(), \OCP\Util::ERROR);
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
				\OCP\Util::writeLog('OCP\Share', \OC_DB::getErrorMessage(), \OCP\Util::ERROR);
			} else {
				while ($row = $result->fetchRow()) {
					$groups[] = $row['share_with'];
				}
			}
/*
			//check for public link shares
			if (!$publicShare) {
				$query = \OC_DB::prepare('
					SELECT `share_with`
					FROM `*PREFIX*share`
					WHERE `item_source` = ? AND `share_type` = ? AND `item_type` IN (\'file\', \'folder\')', 1
				);

				$result = $query->execute(array($source, self::SHARE_TYPE_LINK));

				if (\OCP\DB::isError($result)) {
					\OCP\Util::writeLog('OCP\Share', \OC_DB::getErrorMessage(), \OCP\Util::ERROR);
				} else {
					if ($result->fetchRow()) {
						$publicShare = true;
					}
				}
			}

			//check for remote share
			if (!$remoteShare) {
				$query = \OC_DB::prepare('
					SELECT `share_with`
					FROM `*PREFIX*share`
					WHERE `item_source` = ? AND `share_type` = ? AND `item_type` IN (\'file\', \'folder\')', 1
				);

				$result = $query->execute(array($source, self::SHARE_TYPE_REMOTE));

				if (\OCP\DB::isError($result)) {
					\OCP\Util::writeLog('OCP\Share', \OC_DB::getErrorMessage(), \OCP\Util::ERROR);
				} else {
					if ($result->fetchRow()) {
						$remoteShare = true;
					}
				}
			}
*/
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

		//return array('users' => array_unique($shares), 'public' => $publicShare, 'remote' => $remoteShare);
		return array('users' => array_unique($users), 'groups' => array_unique($groups));
	}

	/**
	 * @param File $file
	 * @param \OC_EventSource $eventSource
	 *
	 * @return array with file content as plain text and metadata
	 */
	public function extractContent (File $file, \OC_EventSource $eventSource = null) {

		$path = $file->getPath();

		if ($eventSource) {
			$eventSource->send('extracting content', $path);
		}

		$doc = new Document($file->getId());

		$value = array(
			'_content_type' => $file->getMimeType(),
			'_content' => base64_encode($file->getContent()),
		);

		$doc->set('file', $value);

		// index with elasticsearch (uses apache tika to extract the file content)
		$this->tempType->addDocument($doc);

		//wait a sec to allow the doc to become searchable
		sleep(1);

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
		return $result;

		//TODO delete temp index

	}

	public function deleteFiles (array $fileIds) {
		$docs = array();
		foreach ($fileIds as $fileId) {
			$docs[] = new Document($fileId);
		}
		if (count($docs) > 0) {
			$result = $this->type->deleteDocuments($docs);
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

	public function updateFile (File $file) {
		$access = $this->getUsersWithReadPermission($file);
		$doc = new Document($file->getId());
		$doc->setData(array('file' => $access));
		try {
			$this->type->updateDocument($doc);
		} catch (NotFoundException $e) {
			// Typically happens when a freshly uploaded file is shared because
			// it has not yet been indexed
			$this->logger->debug("File {$file->getPath()} ({$file->getId()}) not yet in index",
				['app' => 'search_elastic']
			);
		}
	}

} 