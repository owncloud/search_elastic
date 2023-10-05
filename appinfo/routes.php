<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
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

namespace OCA\Search_Elastic\AppInfo;

return ['routes' => [
	['name' => 'admin_settings#loadServers', 'url' => '/settings/servers', 'verb' => 'GET'],
	['name' => 'admin_settings#saveServers', 'url' => '/settings/servers', 'verb' => 'POST'],
	['name' => 'admin_settings#getScanExternalStorages', 'url' => '/settings/scanExternalStorages', 'verb' => 'GET'],
	['name' => 'admin_settings#setScanExternalStorages', 'url' => '/settings/scanExternalStorages', 'verb' => 'POST'],
	['name' => 'admin_settings#checkStatus', 'url' => '/settings/status', 'verb' => 'GET'],
	['name' => 'admin_settings#setup', 'url' => '/setup', 'verb' => 'POST'],
	['name' => 'admin_settings#rescan', 'url' => '/rescan', 'verb' => 'POST'],
	['name' => 'admin_settings#saveConnectors', 'url' => '/settings/connectors/save', 'verb' => 'POST'],
	['name' => 'api#index', 'url' => '/indexer/index', 'verb' => 'GET'],
	['name' => 'api#optimize', 'url' => '/indexer/optimize', 'verb' => 'POST'],
]];
