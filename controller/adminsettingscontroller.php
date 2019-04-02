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
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\SearchElasticService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\AppFramework\ApiController;

class AdminSettingsController extends ApiController {

	/**
	 * @var SearchElasticConfigService
	 */
	private $config;

	/**
	 * @var SearchElasticService
	 */
	private $searchElasticService;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param SearchElasticConfigService $config
	 * @param SearchElasticService $searchElasticService
	 */
	public function __construct(
		$appName,
		IRequest $request,
		SearchElasticConfigService $config,
		SearchElasticService $searchElasticService
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->searchElasticService = $searchElasticService;
	}

	/**
	 * @return JSONResponse
	 */
	public function loadServers() {
		return new JSONResponse([
			SearchElasticConfigService::SERVERS => $this->config->getServers()
		]);
	}

	/**
	 * @param string $servers
	 * @return JSONResponse
	 */
	public function saveServers($servers) {
		$this->config->setServers($servers);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function getScanExternalStorages() {
		return new JSONResponse([
			SearchElasticConfigService::SCAN_EXTERNAL_STORAGE => $this->config->getScanExternalStorageFlag()
		]);
	}

	/**
	 * @param bool $scanExternalStorages
	 * @return JSONResponse
	 */
	public function setScanExternalStorages($scanExternalStorages) {
		$this->config->setScanExternalStorageFlag($scanExternalStorages);
		return new JSONResponse();
	}

	/**
	 * @return JSONResponse
	 */
	public function checkStatus() {
		try {
			if (!$this->searchElasticService->isSetup()) {
				return new JSONResponse(['message' => 'Index not set up'], Http::STATUS_EXPECTATION_FAILED);
			}
		} catch (HttpException $ex) {
			$servers = $this->config->getServers();
			return new JSONResponse(['message' => 'Elasticsearch Server unreachable at '.$servers], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$stats = $this->searchElasticService->getStats();
		return new JSONResponse(['stats' => $stats]);
	}

	/**
	 * @return JSONResponse
	 */
	public function setup() {
		try {
			$this->searchElasticService->setup();
		} catch (\Exception $e) {
			// TODO log exception
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
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
}
