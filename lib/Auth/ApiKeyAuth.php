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

use OCP\Security\ICredentialsManager;

class ApiKeyAuth implements IAuth {
	private $credentialsManager;

	private $requiredAuthKeys = ['apiKey'];

	public function __construct(ICredentialsManager $credentialsManager) {
		$this->credentialsManager = $credentialsManager;
	}

	public function getRequiredAuthKeys(): array {
		return $this->requiredAuthKeys;
	}

	public function saveAuthParams(array $authParams): bool {
		// validation
		if (!isset($authParams['apiKey']) || !\is_string($authParams['apiKey'])) {
			return false;
		}

		$this->credentialsManager->store('', IAuth::CRED_KEY_PREFIX . 'apiKey', $authParams['apiKey']);
		return true;
	}

	public function getAuthParams(): array {
		$authParams = [];

		$value = $this->credentialsManager->retrieve('', IAuth::CRED_KEY_PREFIX . 'apiKey');
		if ($value !== null) {
			$authParams['apiKey'] = $value;
		}

		return $authParams;
	}

	public function clearAuthParams(): void {
		$this->credentialsManager->delete('', IAuth::CRED_KEY_PREFIX . 'apiKey');
	}

	public function maskAuthParams(array $authParams): array {
		$maskedParams = [];
		if (isset($authParams['apiKey'])) {
			$maskedParams['apiKey'] = IAuth::MASKED_VALUE;
		}
		return $maskedParams;
	}
}
