<?php
/**
 * @author Michael Barz <mbarz@owncloud.com>
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
namespace OCA\Search_Elastic;

use OCA\Search_Elastic\AppInfo\Application;
use OCP\IConfig;

class SearchElasticConfigService {
	public const SERVERS = 'servers';
	public const SCAN_EXTERNAL_STORAGE = 'scanExternalStorages';
	public const INDEX_MAX_FILE_SIZE = 'max_size';
	public const INDEX_NO_CONTENT = 'nocontent';
	public const SKIPPED_DIRS = 'skipped_dirs';
	public const NO_CONTENT_GROUP = 'group.nocontent';
	public const APP_MODE = 'mode';
	public const ENABLED_GROUPS = 'group';

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
	 * @param string $key
	 * @param mixed $value
	 */
	public function setValue($key, $value) {
		$this->owncloudConfig->setAppValue(Application::APP_ID, $key, $value);
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getValue($key, $default = '') {
		return $this->owncloudConfig->getAppValue(Application::APP_ID, $key, $default);
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $value
	 */
	public function setUserValue($userId, $key, $value) {
		$this->owncloudConfig->setUserValue($userId, Application::APP_ID, $key, $value);
	}

	/**
	 * @param string $userId
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	public function getUserValue($userId, $key, $default = '') {
		return $this->owncloudConfig->getUserValue($userId, Application::APP_ID, $key, $default);
	}

	/**
	 * @param string $servers comma separated list of servers
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
	 * @param bool $flag
	 */
	public function setScanExternalStorageFlag($flag) {
		$this->setValue(self::SCAN_EXTERNAL_STORAGE, $flag);
	}

	/**
	 * @return bool
	 */
	public function getScanExternalStorageFlag() {
		return $this->getValue(self::SCAN_EXTERNAL_STORAGE, true) === true;
	}

	/**
	 * @param string $maxFileSize
	 */
	public function setMaxFileSizeForIndex($maxFileSize) {
		$this->setValue(self::INDEX_MAX_FILE_SIZE, $maxFileSize);
	}

	/**
	 * @return string
	 */
	public function getMaxFileSizeForIndex() {
		return $this->getValue(self::INDEX_MAX_FILE_SIZE, 10485760);
	}

	/**
	 * @param string $noContentFlag
	 */
	public function setIndexNoContentFlag($noContentFlag) {
		$this->setValue(self::INDEX_NO_CONTENT, $noContentFlag);
	}

	/**
	 * @return mixed
	 */
	public function getIndexNoContentFlag() {
		return $this->getValue(self::INDEX_NO_CONTENT, false);
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function getUserSkippedDirs($userId) {
		return \explode(
			';',
			$this->getUserValue($userId, self::SKIPPED_DIRS, '.git;.svn;.CVS;.bzr')
		);
	}

	/**
	 * @return string
	 */
	public function getGroupNoContentString() {
		return $this->getValue(self::NO_CONTENT_GROUP, '');
	}

	/**
	 * @return array
	 */
	public function getGroupNoContentArray() {
		return \str_getcsv($this->getGroupNoContentString(), ',', '"', "\\");
	}

	/**
	 * @return bool
	 */
	public function shouldContentBeIncluded() {
		$noContent = $this->getIndexNoContentFlag();
		return !($noContent === true
			|| $noContent === 1
			|| $noContent === 'true'
			|| $noContent === '1'
			|| $noContent === 'on');
	}

	/**
	 * @param string $servers
	 * @return array
	 */
	public function parseServers($servers) {
		$serverArr = \explode(',', $servers);
		$results = [];
		foreach ($serverArr as $serverPart) {
			$server = [
				'host' => 'localhost',
				'port' => 9200
			];
			if (str_contains($serverPart, '@')) {
				$this->parseServerWithUserPassAuthentication($serverPart, $server);
			} else {
				$this->parseServerWithoutAuthentication($serverPart, $serve);
			}
			$results[] = $server;
		}
		if (\count($results) === 1) {
			return $results[0];
		}
		return ['servers' => $results];
	}

	/**
	 * Parse server connection with user and password.
	 *
	 * @param string $serverPart
	 * @param array $server
	 */
	private function parseServerWithUserPassAuthentication($serverPart, &$server) {
		$sets = explode('@', $serverPart);
		$authenticationParameters = explode(':', $sets[0]);
		$host = explode(':', $sets[1]);
		if (!empty($host[0])) {
			$server['host'] = $host[0];
		}
		if (!empty($host[1])) {
			$server['port'] = $host[1];
		}
		$server['username'] = $authenticationParameters[0];
		$server['password'] = $authenticationParameters[1];
	}

	/**
	 * Parse server connection without authentication.
	 *
	 * @param string $serverPart
	 * @param array $server
	 */
	private function parseServerWithoutAuthentication($serverPart, &$server) {
		$hostAndPort = \explode(':', \trim($serverPart), 2);
		if (!empty($hostAndPort[0])) {
			$server['host'] = $hostAndPort[0];
		}
		if (!empty($hostAndPort[1])) {
			$server['port'] = (int)$hostAndPort[1];
		}
	}
}
