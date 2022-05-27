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

interface IAuth {
	/**
	 * Constant to be used as prefix if parameters are needed to be saved in the credentials manager.
	 * This is intended to provide a common way to recognize those parameters.
	 * Usage of this constant should be limited to the "Auth" package
	 */
	public const CRED_KEY_PREFIX = 'search_elastic:auth_param:';
	/**
	 * Constant to be used as prefix if parameters are needed to be saved in the appconfig.
	 * This is intended to provide a common way to recognize those parameters.
	 * Usage of this constant should be limited to the "Auth" package
	 */
	public const CONF_KEY_PREFIX = 'auth_param:';
	/**
	 * Expected value to be used for parameters that needs to be masked, such as password or keys.
	 * This constant is expected to be used only within the "Auth" package. The rest of the app
	 * shouldn't need to use this constant and use the masked parameter as a normal one.
	 */
	public const MASKED_VALUE = '**MASKED**';

	/**
	 * Get the required keys that the authParams array needs to have.
	 * @return array a list of keys that must be present, such as ["username", "password"]
	 */
	public function getRequiredAuthKeys(): array;

	/**
	 * Save the authParams. The specific location and security is delegated to the implementation.
	 * Some possible locations are the appconfig or the credentials manager, but other locations
	 * can be decided by the implementation. Security requirements such as encrypting the data
	 * are also delegated to the implementation
	 * @params array<string, string> $authParams a map containing key => value. Keys not present in the
	 * `getRequiredAuthKeys` array should be ignored, and only the keys present should be saved. This
	 * will allow easier cleanup.
	 * @return bool true if the data is saved, false otherwise
	 */
	public function saveAuthParams(array $authParams): bool;

	/**
	 * Get the saved authParams. If there is no value saved for a key, that key shouldn't be present
	 * in the result. Note that only the keys from the `getRequiredAuthKeys` method are expected to
	 * be returned.
	 * @return array<string, string> a map of key => value pairs previously saved from the
	 * `saveAuthParams` method.
	 */
	public function getAuthParams(): array;

	/**
	 * Remove the authParams saved. It's expected that all keys from the `getRequiredAuthKeys` method
	 * are removed. If there is any other additional key saved, it has to be removed as well.
	 */
	public function clearAuthParams(): void;

	/**
	 * Mask the authParams. Passwords and any other critical information should be replaced with the
	 * MASKED_VALUE constant, although each implementation can used its own value. As said, the use of
	 * masked values shouldn't be leaked. Note that some other parameters might not need to be masked,
	 * such as the username, so those are expected to be kept without modification.
	 * It's expected that the masked values come around to be saved with the `savedAuthParams` method,
	 * so that method should be prepared to handle this scenario.
	 * Unexpected keys might be present in the $authParams map. Those keys should be ignored and shouldn't
	 * be present in the result.
	 * @params array<string, string> $authParams a map with key => value, usually coming from the
	 * `getAuthParams` method (no real guarantee)
	 * @return array<string, string> the same map but with the critical values masked. If no value needs
	 * to be masked, the same array should be returned.
	 */
	public function maskAuthParams(array $authParams): array;
}
