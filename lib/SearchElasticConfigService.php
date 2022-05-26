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
use OCA\Search_Elastic\Auth\AuthManager;
use OCP\IConfig;
use OCP\Security\ICredentialsManager;

class SearchElasticConfigService {
	public const SERVERS = 'servers';
	public const SERVER_AUTH = 'server_auth';
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
	 * @var ICredentialsManager
	 */
	private $credentialsManager;
	private $authManager;

	/**
	 * SearchElasticConfigService constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config, ICredentialsManager $credentialsManager, AuthManager $authManager) {
		$this->owncloudConfig = $config;
		$this->credentialsManager = $credentialsManager;
		$this->authManager = $authManager;
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
	 * @param string $auth
	 * @param array $authParams
	 */
	public function setServerAuth(string $auth, array $authParams) {
		$oldAuth = $this->getValue(self::SERVER_AUTH, '');
		if ($oldAuth !== $auth) {
			$oldAuthObj = $this->authManager->getAuthByName($oldAuth);
			if ($oldAuthObj) {
				$oldAuthObj->clearAuthParams();
			}
		}

		$this->setValue(self::SERVER_AUTH, $auth);
		$authObj = $this->authManager->getAuthByName($auth);
		if ($authObj) {
			$authObj->saveAuthParams($authParams);
		}
	}

	/**
	 * @return array
	 */
	public function getServerAuth() {
		$authData = [
			'auth' => $this->getValue(self::SERVER_AUTH, ''),
			'authParams' => [],
		];

		if ($authData['auth'] === '') {
			return $authData;
		}

		$authObj = $this->authManager->getAuthByName($authData['auth']);
		if ($authObj) {
			$authData['authParams'] = $authObj->getAuthParams();
		}
		return $authData;
	}

	public function maskServerAuthData(array $authData) {
		$authObj = $this->authManager->getAuthByName($authData['auth']);
		if ($authObj) {
			$authData['authParams'] = $authObj->maskAuthParams($authData['authParams']);
		}
		return $authData;
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

			// if it's https but not explicit port is set, use port 443
			if ($serverData['transport'] === 'https' && !isset($serverData['port'])) {
				$serverData['port'] = 443;
			}

			$serverAuthData = $this->getServerAuth();
			switch ($serverAuthData['auth']) {
				case '':
				case 'none':
					break;  // no auth -> don't do anything
				case 'userPass':
					$serverData['username'] = $serverAuthData['authParams']['username'];
					$serverData['password'] = $serverAuthData['authParams']['password'];
					break;
				case 'apiKey':
					$serverData['headers'] = [
						'Authorization' => "ApiKey {$serverAuthData['authParams']['apiKey']}",
					];
					break;
			}

			$results[] = $serverData;
		}
		return ['servers' => $results];
	}
}
