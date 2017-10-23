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
use OCA\Search_Elastic\SearchElasticService;
use OCA\Search_Elastic\SearchElasticConfigService;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App {

	const APP_ID = 'search_elastic';

	public function __construct (array $urlParams=array()) {
		parent::__construct('search_elastic', $urlParams);

		$container = $this->getContainer();

		// register Logger as alias for convenience
		$container->registerAlias('Logger', 'OCP\\ILogger');

		// register internal configuration service
		$container->registerService(
			'SearchElasticConfigService',
			function(IAppContainer $appContainer) {
				return new SearchElasticConfigService(
					$appContainer->query('ServerContainer')->getConfig()
				);
			}
		);
		
		/**
		 * SearchElasticService
		 */
		$container->registerService('Elastica',
			function(IAppContainer $appContainer) {
				$config = $appContainer->query('SearchElasticConfigService');
				return new \Elastica\Client($config->getParsedServers());
			}
		);

		$container->registerService('SearchElasticService', function($c) {
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
		$container->registerService('StatusMapper', function($c) {
			return new StatusMapper(
				$c->query('Db'),
				$c->query('SearchElasticConfigService'),
				$c->query('Logger')
			);
		});

		/**
		 * Core
		 */
		$container->registerService('UserId', function($c) {
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			if ($user) {
				return $c->query('ServerContainer')->getUserSession()->getUser()->getUID();
			}
			return false;
		});

		$container->registerService('Db', function($c) {
			return $c->query('ServerContainer')->getDb();
		});

		/**
		 * Controllers
		 */
		$container->registerService('AdminSettingsController', function($c) {
			return new AdminSettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('SearchElasticConfigService'),
				$c->query('SearchElasticService')
			);
		});
	}


}