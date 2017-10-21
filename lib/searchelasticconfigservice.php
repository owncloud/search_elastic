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
	public function getServers() {
		return $this->getValue(self::SERVERS, 'localhost:9200');
	}

	/**
	 * @param $servers commad seperated list of servers
	 */
	public function setServers($servers) {
		$this->setValue('servers', $servers);
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
}
