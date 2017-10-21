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

namespace OCA\Search_Elastic\Controller;

use Elastica\Exception\Connection\HttpException;
use Elastica\Index;
use Elastica\Request;
use Elastica\Type;
use OC\AppFramework\Http;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\ApiController;

class AdminSettingsController extends ApiController {

	const SERVERS = 'servers';
	const SCAN_EXTERNAL_STORAGE = 'scanExternalStorages';
	/**
	 * @var IConfig
	 */
	var $config;
	/**
	 * @var Index
	 */
	var $index;
	/**
	 * @var Index
	 */
	var $contentExtractionIndex;
	/**
	 * @var StatusMapper
	 */
	var $mapper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param Index $index
	 * @param Index $contentExtractionIndex
	 * @param StatusMapper $mapper
	 */
	public function __construct($appName, IRequest $request, IConfig $config, Index $index, Index $contentExtractionIndex, StatusMapper $mapper) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->index = $index;
		$this->contentExtractionIndex = $contentExtractionIndex;
		$this->mapper = $mapper;
	}

	/**
	 * @return JSONResponse
	 */
	public function loadServers() {
		$servers = $this->config->getAppValue($this->appName, self::SERVERS, 'localhost:9200');
		return new JSONResponse(array(self::SERVERS => $servers) );
	}

	/**
	 * @param string $servers
	 * @return JSONResponse
	 */
	public function saveServers($servers) {
		$this->config->setAppValue($this->appName, self::SERVERS, $servers);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function getScanExternalStorages() {
		$scanExternalStorages = $this->config->getAppValue($this->appName, self::SCAN_EXTERNAL_STORAGE, true);
		return new JSONResponse(array(self::SCAN_EXTERNAL_STORAGE => $scanExternalStorages) );
	}

	/**
	 * @param bool $scanExternalStorages
	 * @return JSONResponse
	 */
	public function setScanExternalStorages($scanExternalStorages) {
		$this->config->setAppValue($this->appName, self::SCAN_EXTERNAL_STORAGE, $scanExternalStorages);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function checkStatus() {
		try {
			if (!$this->index->exists()) {
				return new JSONResponse(array('message' => 'Index not set up'), Http::STATUS_EXPECTATION_FAILED);
			}
			if (!$this->contentExtractionIndex->exists()) {
				return new JSONResponse(array('message' => 'Content extraction index not set up'), Http::STATUS_EXPECTATION_FAILED);
			}
			$mapping = $this->contentExtractionIndex->getMapping();
			if (!isset($mapping['file']['properties']['file']['type']) ||
				$mapping['file']['properties']['file']['type'] !== 'attachment'
			) {
				return new JSONResponse(array('message' => 'Content extraction index requires attachment type. Did you install the elasticsearch mapper attachments plugin?'), Http::STATUS_EXPECTATION_FAILED);
			}
		} catch (HttpException $ex) {
			$servers = $this->config->getAppValue($this->appName, self::SERVERS, 'localhost:9200');
			return new JSONResponse(array('message' => 'Elasticsearch Server unreachable at '.$servers), Http::STATUS_SERVICE_UNAVAILABLE);
		}
		$stats = $this->index->getStats()->getData();
		$instanceId = \OC::$server->getSystemConfig()->getValue('instanceid', '');
		$countIndexed = $this->mapper->countIndexed();
		return new JSONResponse(['stats' => [
			'_all'     => $stats['_all'],
			'_shards'  => $stats['_shards'],
			'oc_index' => $stats['indices']["oc-$instanceId"],
			'countIndexed'  => $countIndexed,
		]]);
	}

	/**
	 * @return JSONResponse
	 */
	public function setup() {
		try {
			$this->setUpIndex();
			$this->setUpProcessor();
			$this->mapper->clear();
		} catch (\Exception $e) {
			// TODO log exception
			return new JSONResponse(array('message' => $e->getMessage()), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return $this->checkStatus();
	}

	/**
	 * @return JSONResponse
	 */
	public function rescan() {
		/*
		 * FIXME we need to iterate over all files. how do we access users files in external storages?
		 * It would make more sense to iterate over all storages.
		 * For now the index will be filled by the cronjob
		// we use our own fs setup code to also set the user in the session
		$folder = $container->query('FileUtility')->setUpUserHome($userId);

		if ($folder) {

			$fileIds = $container->query('StatusMapper')->getUnindexed();

			$logger->debug('background job indexing '.count($fileIds).' files for '.$userId );

			$container->query('Client')->indexFiles($fileIds);

		}
		*/
	}

	function setUpProcessor() {
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


		$response = $this->index->getClient()->request("_ingest/pipeline/oc_processor", Request::PUT, $payload);
		//TODO: verify that we setup the processor correctly

	}

	/**
	 * WARNING: will delete the index if it exists
	 */
	function setUpIndex() {
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

	/**
	 * WARNING: will delete the index if it exists
	 */
	function setUpContentExtractionIndex() {
		// the number of shards and replicas should be adjusted as necessary outside of owncloud
		$this->contentExtractionIndex->create(array('index' => array('number_of_shards' => 1, 'number_of_replicas' => 0),), true);

		$type = new Type($this->contentExtractionIndex, 'file');

		$mapping = new Type\Mapping($type, array(
			'file' => array(
				'type' => 'attachment',
				'fields' => [
					'content'        => [ 'store' => true, 'type' => 'text' ],
					'title'          => [ 'store' => true ],
					'date'           => [ 'store' => true ],
					'author'         => [ 'store' => true ],
					'keywords'       => [ 'store' => true ],
					'content_type'   => [ 'store' => true ],
					'content_length' => [ 'store' => true ],
					'language'       => [ 'store' => true ],
				],
			),
		));
		// do not store file in es
		$mapping->setParam('_source', array('excludes' => array('file.content')));
		$type->setMapping($mapping);
	}
}