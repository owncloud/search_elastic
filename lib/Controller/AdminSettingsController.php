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
use OCP\ILogger;
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
	 * @var ILogger
	 */
	private $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		SearchElasticConfigService $config,
		SearchElasticService $searchElasticService,
		ILogger $logger
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->searchElasticService = $searchElasticService;
		$this->logger = $logger;
	}

	public function loadServers(): JSONResponse {
		$authData = $this->config->getServerAuth();
		$authData = $this->config->maskServerAuthData($authData);
		return new JSONResponse([
			SearchElasticConfigService::SERVERS => $this->config->getServers(),
			SearchElasticConfigService::SERVER_AUTH => $authData,
		]);
	}

	public function saveServers(string $servers, string $authType, array $authParams = []): JSONResponse {
		$serverList = \explode(',', $servers);
		$sanitizedServerList = [];
		foreach ($serverList as $server) {
			// validation
			$parsedServer = \parse_url($server);

			$errorMessage = $this->verifyParsedServer($parsedServer);
			if ($errorMessage) {
				return new JSONResponse(['message' => $errorMessage], Http::STATUS_EXPECTATION_FAILED);
			}

			if (!isset($parsedServer['scheme'])) {
				// assume HTTP
				$parsedServer['scheme'] = 'http';
			}

			// build sanitized url
			$sanitizedServerList[] = $this->buildFromParsedUrl($parsedServer);
		}
		$sanitizedServers = \implode(',', $sanitizedServerList);

		$this->config->setServers($sanitizedServers);
		$this->config->setServerAuth($authType, $authParams);
		return new JSONResponse();
	}

	/**
	 * The $parsedServer var is the result of a `parse_url` call. This method will return
	 * a string containing the error message or null if there is no error.
	 */
	private function verifyParsedServer($parsedServer) {
		if ($parsedServer === false) {
			return 'The url format is incorrect.';
		}

		$mustNotBePresentKeys = ['user', 'pass', 'query', 'fragment'];
		foreach ($mustNotBePresentKeys as $key) {
			if (isset($parsedServer[$key])) {
				return 'The url contains components that won\'t be used.';
			}
		}
		if (!isset($parsedServer['host'])) {
			return 'The url must contains at least a host.';
		}

		if (isset($parsedServer['scheme']) && ($parsedServer['scheme'] !== 'http' && $parsedServer['scheme'] !== 'https')) {
			return 'The url contains invalid scheme.';
		}
		return null;
	}

	/**
	 * The $parsedServer var is the result of a `parse_url` call. Only "scheme",
	 * "host", "port" and "path" components will be used, the rest will be ignored.
	 * Note tha "scheme" and "host" are expected to be always present.
	 */
	private function buildFromParsedUrl($parsedServer) {
		// build sanitized url
		$sanitizedServer = "{$parsedServer['scheme']}://{$parsedServer['host']}";
		if (isset($parsedServer['port'])) {
			$sanitizedServer .= ":{$parsedServer['port']}";
		}
		if (isset($parsedServer['path'])) {
			$sanitizedServer .= $parsedServer['path'];
		}
		return $sanitizedServer;
	}

	public function getScanExternalStorages(): JSONResponse {
		return new JSONResponse([
			SearchElasticConfigService::SCAN_EXTERNAL_STORAGE => $this->config->getScanExternalStorageFlag()
		]);
	}

	public function setScanExternalStorages(bool $scanExternalStorages): JSONResponse {
		$this->config->setScanExternalStorageFlag($scanExternalStorages);
		return new JSONResponse();
	}

	public function checkStatus(): JSONResponse {
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

	public function setup(): JSONResponse {
		try {
			$this->searchElasticService->setup();
		} catch (\Exception $e) {
			$this->logger->logException($e);
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_SERVICE_UNAVAILABLE);
		}
		return $this->checkStatus();
	}

	public function rescan(): JSONResponse {
		return new JSONResponse([], Http::STATUS_NOT_IMPLEMENTED);
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
