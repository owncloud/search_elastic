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

use Elastica\Type;
use OCA\Search_Elastic\ContentExtractor;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\Core\Db;
use OCA\Search_Elastic\Core\Logger;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Client;
use OCA\Search_Elastic\Core\Files;
use OCP\AppFramework\App;

class Application extends App {

	/**
	 * @param string $servers
	 * @return array
	 */
	public function parseServers($servers) {
		$serverArr = explode(',', $servers);
		$results = array();
		foreach ($serverArr as $server) {
			list($host, $port) = explode(':', $server, 2);
			$results[] = array('host' => $host, 'port' => $port);
		}
		if (count($results) === 1) {
			return $results[0];
		}
		return array('servers' => $results);
	}


	public function __construct (array $urlParams=array()) {
		parent::__construct('search_elastic', $urlParams);

		$container = $this->getContainer();

		//add 3rdparty composer autoloader
		require_once __DIR__ . '/../3rdparty/autoload.php';

		/**
		 * Client
		 */
		$container->registerService('Elastica', function($c) {
			/** @var \OCP\IConfig $config */
			$config = $c->query('ServerContainer')->getConfig();
			$elasticaConfig = $this->parseServers($config->getAppValue('search_elastic', 'servers', 'localhost:9200'));
			return new \Elastica\Client($elasticaConfig);
		});

		$container->registerService('Index', function($c) {
			return $c->query('Elastica')->getIndex('owncloud');
		});

		$container->registerService('ContentExtractionIndex', function($c) {
			$index = $c->query('Elastica')->getIndex('owncloud_temp_ce');
			return $index;
		});

		$container->registerService('Client', function($c) {
			return new Client(
				$c->getServer(),
				$c->query('Index'),
				$c->query('ContentExtractionIndex'),
				$c->query('SkippedDirs'),
				$c->query('StatusMapper'),
				$c->query('Logger')
			);
		});

		$container->registerService('SkippedDirs', function($c) {
			/** @var \OCP\IConfig $config */
			$config = $c->query('ServerContainer')->getConfig();
			return explode(
				';',
				$config->getUserValue($c->query('UserId'), 'search_elastic', 'skipped_dirs', '.git;.svn;.CVS;.bzr')
			);
		});

		/**
		 * Mappers
		 */
		$container->registerService('StatusMapper', function($c) {
			return new StatusMapper(
				$c->query('Db'),
				$c->query('Logger'),
				$c->query('OCP\IConfig')->getAppValue('search_elastic', 'scanExternalStorages', true)
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

		$container->registerService('Logger', function($c) {
			return new Logger(
				$c->query('AppName'),
				$c->query('ServerContainer')->getLogger()
			);
		});

		$container->registerService('Db', function($c) {
			return $c->query('ServerContainer')->getDb();
		});

		$container->registerService('FileUtility', function($c) {
			return new Files(
				$c->query('ServerContainer')->getUserManager(),
				$c->query('ServerContainer')->getUserSession(),
				$c->query('RootFolder')
			);
		});

		$container->registerService('RootFolder', function($c) {
			return $c->query('ServerContainer')->getRootFolder();
		});

		$container->registerService('UserFolder', function($c) {
			return $c->query('ServerContainer')->getUserFolder();
		});

		/**
		 * Controllers
		 */
		$container->registerService('AdminSettingsController', function($c) {
			return new AdminSettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('OCP\IConfig'),
				$c->query('Index'),
				$c->query('ContentExtractionIndex'),
				$c->query('StatusMapper')
			);
		});
	}


}