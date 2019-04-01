<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014-2016 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\AppInfo;

use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Hooks\Files;
use OCA\Search_Elastic\SearchElasticService;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App {
	const APP_ID = 'search_elastic';

	/** @var bool */
	private $isSearchProviderRegistered = false;

	public function __construct(array $urlParams=[]) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		// register Logger as alias for convenience
		$container->registerAlias('Logger', 'OCP\\ILogger');

		// register internal configuration service
		$container->registerService(
			'SearchElasticConfigService',
			function (IAppContainer $appContainer) {
				return new SearchElasticConfigService(
					$appContainer->query('ServerContainer')->getConfig()
				);
			}
		);
		
		/**
		 * SearchElasticService
		 */
		$container->registerService('Elastica',
			function (IAppContainer $appContainer) {
				$config = $appContainer->query('SearchElasticConfigService');
				return new \Elastica\Client($config->getParsedServers());
			}
		);

		$container->registerService('SearchElasticService', function ($c) {
			return new SearchElasticService(
				$c->getServer(),
				$c->query('StatusMapper'),
				$c->query('Logger'),
				$c->query('Elastica'),
				$c->query('SearchElasticConfigService'),
				$c->getServer()->getSystemConfig()->getValue('instanceid', '')
			);
		});

		/**
		 * Mappers
		 */
		$container->registerService('StatusMapper', function ($c) {
			return new StatusMapper(
				$c->query('Db'),
				$c->query('SearchElasticConfigService'),
				$c->query('Logger')
			);
		});

		/**
		 * Core
		 */
		$container->registerService('UserId', function ($c) {
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			if ($user) {
				return $c->query('ServerContainer')->getUserSession()->getUser()->getUID();
			}
			return false;
		});

		$container->registerService('Db', function ($c) {
			return $c->query('ServerContainer')->getDb();
		});

		/**
		 * Controllers
		 */
		$container->registerService('AdminSettingsController', function ($c) {
			return new AdminSettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('SearchElasticConfigService'),
				$c->query('SearchElasticService')
			);
		});
	}

	public function init() {
		if ($this->isActive() === true) {
			$this->initFrontEnd();
			$this->registerSearchProvider();
			$this->registerHooks();
			// add background job for deletion
			$server = $this->getContainer()->getServer();
			$server->getJobList()->add(new \OCA\Search_Elastic\Jobs\DeleteJob());
		}
	}

	public function registerSearchProvider() {
		if ($this->isSearchProviderRegistered === true
			|| $this->isActive() !== true
		) {
			return;
		}

		$server = $this->getContainer()->getServer();
		$config = $server->getConfig();
		$group = $config->getAppValue(self::APP_ID, SearchElasticConfigService::ENABLED_GROUPS, null);
		if (empty($group) || (
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
	 * @return bool
	 */
	private function isActive() {
		$config = $this->getContainer()->getServer()->getConfig();
		$mode = $config->getAppValue(self::APP_ID, SearchElasticConfigService::APP_MODE, 'active');
		return $mode === 'active';
	}
	
	private function initFrontEnd() {
		\OCP\Util::addScript(self::APP_ID, 'search');
		\OCP\Util::addStyle(self::APP_ID, 'results');
	}
	
	private function registerHooks() {
		$server = $this->getContainer()->getServer();
		$eventDispatcher = $server->getEventDispatcher();
		$eventDispatcher->addListener('user.afterlogin', [$this, 'registerSearchProvider']);
		
		$fileHook = new Files();
		$eventDispatcher->addListener('file.aftercreate', [$fileHook, 'contentChanged']);
		$eventDispatcher->addListener('file.afterupdate', [$fileHook, 'contentChanged']);

		//connect to the filesystem for rename
		\OCP\Util::connectHook(
			\OC\Files\Filesystem::CLASSNAME,
			\OC\Files\Filesystem::signal_post_rename,
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_post_rename);

		//listen for file shares to update read permission in index
		\OCP\Util::connectHook(
			'OCP\Share',
			'post_shared',
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_share);

		//listen for file un shares to update read permission in index
		\OCP\Util::connectHook(
			'OCP\Share',
			'post_unshare',
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_share);

		//connect to the filesystem for delete
		\OCP\Util::connectHook(
			\OC\Files\Filesystem::CLASSNAME,
			\OC\Files\Filesystem::signal_delete,
			'OCA\Search_Elastic\Hooks\Files',
			Files::handle_delete);
	}
}
