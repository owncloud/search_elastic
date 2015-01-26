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

use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
use Elastica\Type;
use Elastica\Document;
use OCA\Search_Elastic\Db\StatusMapper;
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

	public function autocreateIndexes () {
		// only check if autocreate is true, set to false after creating
		if ( $this->server->getConfig()->getSystemValue('elasticsearch_autocreate', true) ) {
			try {
				if (!$this->index->exists()) {
					$this->setUpIndex();
				}
			} catch (\Exception $ex) {
				$this->logger->warning('index \''.$this->index->getName().'\' already exists');
			}
			try {
				if (!$this->tempIndex->exists()) {
					$this->setUpTempIndex();
				}
			} catch (\Exception $ex) {
				$this->logger->warning('index \''.$this->tempIndex->getName().'\' already exists');
			}
			$this->server->getConfig()->setSystemValue('elasticsearch_autocreate', false);
		}
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

				$this->mapper->markVanished($fileStatus);

			} catch (NotIndexedException $e) {

				$this->mapper->markUnIndexed($fileStatus);

			} catch (SkippedException $e) {

				$this->mapper->markSkipped($fileStatus);
				$this->logger->debug( $e->getMessage() );

			} catch (\Exception $e) {
				//sqlite might report database locked errors when stock filescan is in progress
				//this also catches db locked exception that might come up when using sqlite
				$this->logger->error($e->getMessage() . ' Trace:\n' . $e->getTraceAsString() );
				$this->mapper->markError($fileStatus);
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
	 * @return Node
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
				$data['_name'] = $file->getPath();
				$data['users'] = $this->getUsersWithReadPermission($file);
				$data['mtime'] = $file->getMTime();

				$doc1 = new Document($file->getId());

				$doc1->set('file', $data);
				$this->type->addDocument($doc1);
			}

		}

		return true;

	}

	public function getUsersWithReadPermission(File $file) {
		$owner = $this->server->getUserSession()->getUser()->getUID();
		// get path for lookup in sharing
		$path = $file->getInternalPath();
		//TODO test this hack with subdirs and other storages like objectstore and files_external
		if ($file->getStorage()->instanceOfStorage('\OC\Files\Storage\Home') && substr($path, 0, 6) === 'files/') {
			$path = substr($path, 6);
		}
		$result = \OCP\Share::getUsersSharingFile( $path, $owner, true );
		if (isset($result['users'])) {
			return $result['users'];
		} else {
			return array($owner);
		}
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
			'_name' => $file->getPath(),
			'_content' => base64_encode($file->getContent()),
		);

		$doc->set('file', $value);

		// index with elasticsearch (uses apache tika to extract the file content)
		$this->tempType->addDocument($doc);

		//wait a sec to allow the doc to become searchable
		sleep(1);

		// now get the file content
		$response = $this->tempType->request(urlencode($file->getId()).'?fields=file,file.title,file.date,file.author,file.keywords,file.content_type,file.content_length,file.language', Request::GET, array(), array());

		$data = $response->getData();
		$result = array();
		if (isset($data['fields'])) {
			if (isset($data['fields']['file'])) {
				$result['content'] = $data['fields']['file'][0];
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

	public function updateFile ($fileId) {
		$file = $this->getFileForId($fileId);
		$users = $this->getUsersWithReadPermission($file);
		$doc = new Document($fileId);
		$doc->setData(array('file' => array('users' => $users)));
		$this->type->updateDocument($doc);

	}

} 