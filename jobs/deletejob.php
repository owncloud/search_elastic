<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2015 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Jobs;

use OCA\Search_Elastic\AppInfo\Application;
use OC\BackgroundJob\TimedJob;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\SearchElasticService;

class DeleteJob extends TimedJob {

	public function  __construct() {
		//execute once a minute
		$this->setInterval(60);
	}

	/**
	 * @param array $arguments
	 */
	public function run($arguments){

		$app = new Application();
		$container = $app->getContainer();

		$logger = \OC::$server->getLogger();

		/** @var SearchElasticService $searchElasticService */
		$searchElasticService = $container->query('SearchElasticService');

		$logger->debug('removing deleted files', ['app' => 'search_elastic'] );

		/** @var StatusMapper $mapper */
		$mapper = $container->query('StatusMapper');

		$deletedIds = $mapper->getDeleted();

		if (!empty($deletedIds)) {
			$logger->debug(
				count($deletedIds).' fileids need to be removed:'.
				'( '.implode(';',$deletedIds).' )',
				['app' => 'search_elastic']
			);

			//delete from status table
			$deletedInDb = $mapper->deleteIds($deletedIds);
			$deletedInIndex = $searchElasticService->deleteFiles($deletedIds);
			$logger->debug(
				"removed $deletedInDb ids from status table and $deletedInIndex documents from index",
				['app' => 'search_elastic']
			);
		}
 	}
}
