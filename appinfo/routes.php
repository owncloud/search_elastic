<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\AppInfo;

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
$application = new Application();

$application->registerRoutes($this, array('routes' => array(
	array('name' => 'admin_settings#loadServers', 'url' => '/settings/servers', 'verb' => 'GET'),
	array('name' => 'admin_settings#saveServers', 'url' => '/settings/servers', 'verb' => 'POST'),
	array('name' => 'admin_settings#getScanExternalStorages', 'url' => '/settings/scanExternalStorages', 'verb' => 'GET'),
	array('name' => 'admin_settings#setScanExternalStorages', 'url' => '/settings/scanExternalStorages', 'verb' => 'POST'),
	array('name' => 'admin_settings#checkStatus', 'url' => '/settings/status', 'verb' => 'GET'),
	array('name' => 'admin_settings#setup', 'url' => '/setup', 'verb' => 'POST'),
	array('name' => 'admin_settings#rescan', 'url' => '/rescan', 'verb' => 'POST'),
	array('name' => 'api#index', 'url' => '/indexer/index', 'verb' => 'GET'),
	array('name' => 'api#optimize', 'url' => '/indexer/optimize', 'verb' => 'POST'),
)));
