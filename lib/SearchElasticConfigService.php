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
use OCP\Security\ICredentialsManager;

class SearchElasticConfigService {
	public const SERVERS = 'servers';
	public const SERVER_USER = 'server_user';
	public const SERVER_PASSWORD = 'server_password';
	public const SCAN_EXTERNAL_STORAGE = 'scanExternalStorages';
	public const INDEX_MAX_FILE_SIZE = 'max_size';
	public const INDEX_NO_CONTENT = 'nocontent';
	public const SKIPPED_DIRS = 'skipped_dirs';
	public const NO_CONTENT_GROUP = 'group.nocontent';
	public const APP_MODE = 'mode';
	public const ENABLED_GROUPS = 'group';

	private const PASSWORD_KEY = 'search_elastic:pass';

	/**
	 * @var IConfig
	 */
	private $owncloudConfig;
	/**
	 * @var ICredentialsManager
	 */
	private $credentialsManager;

	/**
	 * SearchElasticConfigService constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config, ICredentialsManager $credentialsManager) {
		$this->owncloudConfig = $config;
		$this->credentialsManager = $credentialsManager;
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
	 * @param string $user
	 */
	public function setServerUser($user) {
		$this->setValue(self::SERVER_USER, $user);
	}

	/**
	 * @return string
	 */
	public function getServerUser() {
		return $this->getValue(self::SERVER_USER, '');
	}

	/**
	 * @param string $password
	 */
	public function setServerPassword($password) {
		$this->credentialsManager->store('', self::PASSWORD_KEY, $password);
	}

	/**
	 * @return string
	 */
	public function getServerPassword() {
		$password = $this->credentialsManager->retrieve('', self::PASSWORD_KEY);
		if ($password === null) {
			$password = '';
		}
		return $password;
	}

	/**
	 * Returns an array of servers
	 * @return array
	 */
	public function getParsedServers() {
		return $this->parseServers();
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
		return $this->getValue(self::SCAN_EXTERNAL_STORAGE, true) === "1";
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
	 * @return array
	 */
	public function parseServers() {
		$servers = $this->getServers();
		$serverList = \explode(',', $servers);

		if ($this->getServerUser() !== '') {
			$username = $this->getServerUser();
			$password = $this->getServerPassword();
		}

		$results = [];
		foreach ($serverList as $server) {
			$parsedServer = \parse_url($server);

			$serverData = [];

			if (isset($parsedServer['host'])) {
				$serverData['host'] = $parsedServer['host'];
			}

			if (isset($parsedServer['port'])) {
				$serverData['port'] = $parsedServer['port'];
			}

			if (isset($parsedServer['scheme'])) {
				$serverData['transport'] = $parsedServer['scheme'];
			} else {
				$serverData['transport'] = 'http';
			}

			if (isset($parsedServer['path'])) {
				$serverData['path'] = \ltrim($parsedServer['path'], '/');
			}

			if (isset($username, $password)) {
				$serverData['username'] = $username;
				$serverData['password'] = $password;
			}

			$results[] = $serverData;
		}
		return ['servers' => $results];
	}
}
