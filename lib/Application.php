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

namespace OCA\Search_Elastic;

use Elastica\Client as ElasticaClient;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Hooks\Files;
use OCA\Search_Elastic\Jobs\DeleteJob;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\IConfig;
use OCP\IDb;
use OCP\ILogger;
use OCP\Share\Events\AcceptShare;

/**
 * Class Application
 *
 * @package OCA\Search_Elastic
 */
class Application extends App {
	const APP_ID = 'search_elastic';

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

		// register internal configuration service
		$container->registerService(
			SearchElasticConfigService::class,
			function (IAppContainer $appContainer) {
				return new SearchElasticConfigService(
					$appContainer->query('ServerContainer')->getConfig()
				);
			}
		);
		
		/**
		 * Elastica
		 */
		$container->registerService(ElasticaClient::class,
			function (IAppContainer $appContainer) {
				$config = $appContainer->query(SearchElasticConfigService::class);
				return new ElasticaClient($config->getParsedServers());
			}
		);

		/**
		 * Mapper
		 */
		$container->registerService(StatusMapper::class, function (IAppContainer $c) {
			return new StatusMapper(
				$c->query(IDb::class),
				$c->query(SearchElasticConfigService::class),
				$c->query(ILogger::class)
			);
		});

		/**
		 * SearchElasticService
		 */
		$container->registerService(SearchElasticService::class, function (IAppContainer $c) {
			return new SearchElasticService(
				$c->query(IConfig::class),
				$c->query(StatusMapper::class),
				$c->query(ILogger::class),
				$c->query(ElasticaClient::class),
				$c->query(SearchElasticConfigService::class)
			);
		});

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

		/**
		 * Controllers
		 */
		$container->registerService(AdminSettingsController::class, function (IAppContainer $c) {
			return new AdminSettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query(SearchElasticConfigService::class),
				$c->query(SearchElasticService::class)
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
