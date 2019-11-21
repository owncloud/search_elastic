<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Saugat Pachhai <suagatchhetri@outlook.com>
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
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

namespace OCA\Search_Elastic\Controller;

use Elastica\Exception\Connection\HttpException;
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
	 * @suppress PhanTypeMissingReturn FIXME
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
