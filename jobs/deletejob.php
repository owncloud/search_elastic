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

use Elastica\Index;
use OCA\Search_Elastic\AppInfo\Application;
use OC\BackgroundJob\TimedJob;
use OCA\Search_Elastic\Db\StatusMapper;

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

		$logger->debug('removing deleted files', ['app' => 'search_elastic'] );

		/** @var StatusMapper $mapper */
		$mapper = $container->query('StatusMapper');

		$deletedIds = $mapper->getDeleted();

		if (!empty($deletedIds)) {
			$logger->debug(
				count($deletedIds).' fileids need to be removed',
				['app' => 'search_elastic']
			);

			//delete from status table
			$deletedInDb = $mapper->deleteIds($deletedIds);

			$deletedDocuments = array();
			foreach ($deletedIds as $fileId) {
				$logger->debug(
					'deleting index document for (' . $fileId . ')',
					['app' => 'search_elastic']
				);

				$deletedDocuments[] = new \Elastica\Document($fileId, array(), 'file');
			}
			//delete from elasticsearch
			/** @var Index $index */
			$index = $container->query('Index');
			$response = $index->deleteDocuments($deletedDocuments);

			$deletedInIndex = $response->count();
			$logger->debug(
				"removed $deletedInDb ids from status table and $deletedInIndex documents from index",
				['app' => 'search_elastic']
			);
		}
 	}
}
