<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Sujith H <sharidasan@owncloud.com>
 * @author VicDeo <victor.dubiniuk@gmail.com>
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

use Elastica\Client as ElasticaClient;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Hooks\Files;
use OCA\Search_Elastic\Jobs\DeleteJob;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCA\Search_Elastic\SearchElasticService;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\ILogger;
use OCP\Share\Events\AcceptShare;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Class Application
 *
 * @package OCA\Search_Elastic
 */
class Application extends App {
	public const APP_ID = 'search_elastic';

	/**
	 * @var bool
	 */
	private $isSearchProviderRegistered = false;

	/**
	 * Application constructor.
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams=[]) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		/**
		 * Elastica
		 */
		$container->registerService(ElasticaClient::class,
			function (IAppContainer $appContainer) {
				$config = $appContainer->query(SearchElasticConfigService::class);
				$logger = null;
				// $logger = $appContainer->query(ElasticLogger::class);
				return new ElasticaClient($config->getParsedServers(), null, $logger);
			}
		);

		/**
		 * Core
		 */
		$container->registerService('UserId', function (IAppContainer $c) {
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			if ($user) {
				return $c->query('ServerContainer')->getUserSession()->getUser()->getUID();
			}
			return false;
		});
	}

	public function init() {
		if ($this->isActive() === true) {
			$this->initFrontEnd();
			$this->registerSearchProvider();
			$this->registerHooks();
			// add background job for deletion
			$server = $this->getContainer()->getServer();
			$container = $this->getContainer();
			$server->getJobList()->add(new DeleteJob(
				$container->query(ILogger::class),
				$container->query(SearchElasticService::class),
				$container->query(StatusMapper::class)
			));
		}
	}

	/**
	 * Register Search Provider
	 *
	 * @return void
	 */
	public function registerSearchProvider() {
		if ($this->isSearchProviderRegistered === true
			|| $this->isActive() !== true
		) {
			return;
		}

		$server = $this->getContainer()->getServer();
		$config = $server->getConfig();
		$group = $config->getAppValue(self::APP_ID, SearchElasticConfigService::ENABLED_GROUPS, null);
		$isAdmin = false;
		if ($server->getUserSession()->isLoggedIn()) {
			$isAdmin = $server->getGroupManager()->isAdmin($server->getUserSession()->getUser()->getUID());
		}

		if (empty($group) || $isAdmin || (
				$server->getUserSession()->getUser()
				&& $server->getGroupManager()->isInGroup(
					$server->getUserSession()->getUser()->getUID(), $group
				)
			)
		) {
			$this->isSearchProviderRegistered = true;
			$server->getSearch()->removeProvider('OC\Search\Provider\File');
			$server->getSearch()->registerProvider('OCA\Search_Elastic\Search\ElasticSearchProvider', ['apps' => ['files']]);
		}
	}

	/**
	 * Check if active App Mode enabled
	 *
	 * @return bool
	 */
	private function isActive() {
		$config = $this->getContainer()->getServer()->getConfig();
		$mode = $config->getAppValue(self::APP_ID, SearchElasticConfigService::APP_MODE, 'active');
		return $mode === 'active';
	}

	/**
	 * Add Frontend assets
	 *
	 * @return void
	 */
	private function initFrontEnd() {
		\OCP\Util::addScript(self::APP_ID, 'search');
		\OCP\Util::addStyle(self::APP_ID, 'results');
	}

	/**
	 * Register the Hooks to trigger re-indexing
	 *
	 * @return void
	 */
	private function registerHooks() {
		$server = $this->getContainer()->getServer();
		$eventDispatcher = $server->getEventDispatcher();
		$eventDispatcher->addListener('user.afterlogin', [$this, 'registerSearchProvider']);

		$fileHook = new Files();
		$eventDispatcher->addListener('file.aftercreate', [$fileHook, 'contentChanged']);
		$eventDispatcher->addListener('file.afterupdate', [$fileHook, 'contentChanged']);
		$eventDispatcher->addListener(AcceptShare::class, [$fileHook, 'federatedShareUpdate']);

		// Connect to the trashbin restore
		\OCP\Util::connectHook(
			'\OCA\Files_Trashbin\Trashbin',
			'post_restore',
			'OCA\Search_Elastic\Hooks\Files',
			'trashbinRestoreUpdate'
		);

		// Connect to the file version restore
		\OCP\Util::connectHook(
			'\OCP\Versions',
			'rollback',
			'OCA\Search_Elastic\Hooks\Files',
			'fileVersionRestoreUpdate'
		);

		//connect to the filesystem for rename
		\OCP\Util::connectHook(
			\OC\Files\Filesystem::CLASSNAME,
			\OC\Files\Filesystem::signal_post_rename,
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_post_rename
		);

		//listen for file shares to update read permission in index
		\OCP\Util::connectHook(
			'OCP\Share',
			'post_shared',
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_share
		);

		//listen for file un shares to update read permission in index
		\OCP\Util::connectHook(
			'OCP\Share',
			'post_unshare',
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_share
		);

		//connect to the filesystem for delete
		\OCP\Util::connectHook(
			\OC\Files\Filesystem::CLASSNAME,
			\OC\Files\Filesystem::signal_delete,
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_delete
		);
	}
}
