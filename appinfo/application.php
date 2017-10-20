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

use Elastica\Type;
use OCA\Search_Elastic\Controller\AdminSettingsController;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\Client;
use OCP\AppFramework\App;

class Application extends App {

	/**
	 * @param string $servers
	 * @return array
	 */
	public function parseServers($servers) {
		$serverArr = explode(',', $servers);
		$results = [];
		foreach ($serverArr as $serverPart) {
			$hostAndPort = explode(':', trim($serverPart), 2);
			$server = [
				'host' => 'localhost',
				'port' => 9200
			];
			if (!empty($hostAndPort[0])) {
				$server['host'] = $hostAndPort[0];
			}
			if (!empty($hostAndPort[1])) {
				$server['port'] = (int)$hostAndPort[1];
			}
			$results[] = $server;
		}
		if (count($results) === 1) {
			return $results[0];
		}
		return array('servers' => $results);
	}


	public function __construct (array $urlParams=array()) {
		parent::__construct('search_elastic', $urlParams);

		$container = $this->getContainer();

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
			$instanceId = \OC::$server->getSystemConfig()->getValue('instanceid', '');
			return $c->query('Elastica')->getIndex('oc-'.$instanceId);
		});

		$container->registerService('ContentExtractionIndex', function($c) {
			$instanceId = \OC::$server->getSystemConfig()->getValue('instanceid', '');
			$index = $c->query('Elastica')->getIndex("oc-$instanceId-temp-ce");
			return $index;
		});

		$container->registerService('Client', function($c) {
			return new Client(
				$c->getServer(),
				$c->query('Index'),
				$c->query('ContentExtractionIndex'),
				$c->query('StatusMapper'),
				\OC::$server->getLogger(),
				\OC::$server->getConfig()
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
				\OC::$server->getLogger(),
				\OC::$server->getConfig()->getAppValue('search_elastic', 'scanExternalStorages', true)
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
				\OC::$server->getConfig(),
				$c->query('Index'),
				$c->query('ContentExtractionIndex'),
				$c->query('StatusMapper')
			);
		});
	}


}