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

/**
 * Class to provide a simple registration mechanism for the IAuth classes
 * The registration is expected to happen in the
 * \OCA\Search_Elastic\AppInfo\Application class to be easier to inject all
 * the dependencies. Registering additional class out of the app initialization
 * is possible, but might become problematic
 */
class AuthManager {
	/** @var array<string, IAuth> */
	private $authMap = [];

	/**
	 * Register the IAuth class under the given name.
	 * If the name is being used, the new IAuth will override the previous one
	 * @param string $name the name that will be used to get the IAuth
	 * @param IAuth $auth the IAuth to be registered
	 */
	public function registerAuthMech(string $name, IAuth $auth) {
		$this->authMap[$name] = $auth;
	}

	/**
	 * Get the IAuth object registered under the specified name, or null otherwise.
	 * @params string $name the name under the IAuth object has been registered
	 * @return IAuth|null the IAuth object or null if there is no IAuth object registered
	 * under that name.
	 */
	public function getAuthByName(string $name): ?IAuth {
		if (!isset($this->authMap[$name])) {
			return null;
		}
		return $this->authMap[$name];
	}
}
