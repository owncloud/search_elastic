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
 * Class implementing a "no authentication needed" mechanism
 * No authentication parameters will be saved, and masking won't do anything
 */
class NullAuth implements IAuth {
	/**
	 * @inheritDoc
	 */
	public function getRequiredAuthKeys(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function saveAuthParams(array $authParams): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAuthParams(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function clearAuthParams(): void {
	}

	/**
	 * @inheritDoc
	 */
	public function maskAuthParams(array $authParams): array {
		return [];
	}
}
