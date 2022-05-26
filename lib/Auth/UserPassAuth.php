<?php
/**
 * @author Juan Pablo Villafáñez <jvillafanez@owncloud.com>
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
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

namespace OCA\Search_Elastic\Auth;

use OCA\Search_Elastic\AppInfo\Application;
use OCP\Security\ICredentialsManager;
use OCP\IConfig;

class UserPassAuth implements IAuth {
	private $credentialsManager;
	private $config;

	private $requiredAuthKeys = ['username', 'password'];

	public function __construct(ICredentialsManager $credentialsManager, IConfig $config) {
		$this->credentialsManager = $credentialsManager;
		$this->config = $config;
	}

	public function getRequiredAuthKeys(): array {
		return $this->requiredAuthKeys;
	}

	public function saveAuthParams(array $authParams): bool {
		// validation
		foreach ($this->requiredAuthKeys as $requiredKey) {
			if (!isset($authParams[$requiredKey]) || !\is_string($authParams[$requiredKey])) {
				return false;
			}
		}

		foreach ($this->requiredAuthKeys as $requiredKey) {
			if ($requiredKey === 'password') {
				// the password will be stored in the credentials manager
				$this->credentialsManager->store('', IAuth::CRED_KEY_PREFIX . $requiredKey, $authParams[$requiredKey]);
			} else {
				$this->config->setAppValue(Application::APP_ID, IAuth::CONF_KEY_PREFIX . $requiredKey, $authParams[$requiredKey]);
			}
		}
		return true;
	}

	public function getAuthParams(): array {
		$authParams = [];
		foreach ($this->requiredAuthKeys as $requiredKey) {
			if ($requiredKey === 'password') {
				// the password will be stored in the credentials manager
				$value = $this->credentialsManager->retrieve('', IAuth::CRED_KEY_PREFIX . $requiredKey);
			} else {
				$value = $this->config->getAppValue(Application::APP_ID, IAuth::CONF_KEY_PREFIX . $requiredKey, null);
			}

			if ($value !== null) {
				$authParams[$requiredKey] = $value;
			}
		}
		return $authParams;
	}

	public function clearAuthParams(): void {
		foreach ($this->requiredAuthKeys as $requiredKey) {
			if ($requiredKey === 'password') {
				// the password will be stored in the credentials manager
				$this->credentialsManager->delete('', IAuth::CRED_KEY_PREFIX . $requiredKey);
			} else {
				$this->config->deleteAppValue(Application::APP_ID, IAuth::CONF_KEY_PREFIX . $requiredKey);
			}
		}
	}

	public function maskAuthParams(array $authParams): array {
		$maskedParams = [];
		foreach ($this->requiredAuthKeys as $requiredKey) {
			if (!isset($authParams[$requiredKey])) {
				continue;
			}

			if ($requiredKey === 'password') {
				// the password needs to be masked
				$maskedParams[$requiredKey] = IAuth::MASKED_VALUE;
			} else {
				$maskedParams[$requiredKey] = $authParams[$requiredKey];
			}
		}
		return $maskedParams;
	}
}
