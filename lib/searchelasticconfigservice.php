<?php


namespace OCA\Search_Elastic;

use OCA\Search_Elastic\AppInfo\Application;
use OCP\IConfig;

class SearchElasticConfigService {

	const SERVERS = 'servers';
	const SCAN_EXTERNAL_STORAGE = 'scanExternalStorages';

	/**
	 * @var IConfig
	 */
	private $owncloudConfig;

	/**
	 * SearchElasticConfigService constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->owncloudConfig = $config;
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function setValue($key, $value) {
		$this->owncloudConfig->setAppValue(Application::APP_ID, $key, $value);
	}

	/**
	 * @param $key
	 * @param string $default
	 * @return string
	 */
	public function getValue($key, $default = '') {
		return $this->owncloudConfig->getAppValue(Application::APP_ID, $key, $default);
	}

	/**
	 * @return string comma separated list of servers
	 */
	}

	/**
	 * @param $servers commad seperated list of servers
	 */
	public function setServers($servers) {
		$this->setValue('servers', $servers);
	}

	/**
	 * @return string comma separated list of servers
	 */
	public function getServers() {
		return $this->getValue(self::SERVERS, 'localhost:9200');
	}

	/**
	 * Returns an array of servers
	 * @return array
	 */
	public function getParsedServers() {
		return $this->parseServers($this->getServers());
	}

	/**
	 * @param $flag
	 */
	public function setScanExternalStorageFlag($flag) {
		$this->setValue(self::SCAN_EXTERNAL_STORAGE, $flag);
	}

	/**
	 * @return string
	 */
	public function getScanExternalStorageFlag() {
		return $this->getValue(self::SCAN_EXTERNAL_STORAGE, true);
	}
	/**
	 * @param string $servers
	 * @return array
	 */
	public function parseServers($servers) {
		$serverArr = explode(',', $servers);
		$results = [];
		foreach ($serverArr as $serverPart) {
			$hostAndPort = explode(':', trim($serverPart), 2);
			$server = [
				'host' => 'localhost',
				'port' => 9200
			];
			if (!empty($hostAndPort[0])) {
				$server['host'] = $hostAndPort[0];
			}
			if (!empty($hostAndPort[1])) {
				$server['port'] = (int)$hostAndPort[1];
			}
			$results[] = $server;
		}
		if (count($results) === 1) {
			return $results[0];
		}
		return array('servers' => $results);
	}
}
