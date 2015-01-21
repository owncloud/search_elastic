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
use OCA\Search_Elastic\Core\Logger;
use OC\BackgroundJob\TimedJob;
use OCA\Search_Elastic\Db\Status;
use OCA\Search_Elastic\Db\StatusMapper;

class DeleteJob extends TimedJob {

	public function __construct() {
		//execute once a minute
		$this->setInterval(60);
	}

	/**
	 * @param array $arguments
	 */
	public function run($arguments){

		$app = new Application();
		$container = $app->getContainer();

		/** @var Logger $logger */
		$logger = $container->query('Logger');


		if (empty($arguments['user'])) {
			$logger->debug('indexer job did not receive user in arguments: '.json_encode($arguments) );
			return;
		}

		$userId = $arguments['user'];
		$logger->debug('background job optimizing index for '.$userId );

		/** @var Index $index */
		$index = $container->query('Index');

		/** @var StatusMapper $mapper */
		$mapper = $container->query('StatusMapper');

		$deletedIds = $mapper->getDeleted();
		$count = 0;
		if (!empty($deletedIds)) {

			$deletedDocuments = array();
			foreach ($deletedIds as $fileId) {
				$logger->debug('deleting status for (' . $fileId . ') ');
				//delete status
				//FIXME use IN in sql
				$status = new Status($fileId);
				$mapper->delete($status);

				$deletedDocuments[] = new \Elastica\Document($fileId, array(), 'file');
			}
			//delete from elasticsearch
			$response = $index->deleteDocuments($deletedDocuments);

			$count = $response->count();
		}
		$logger->debug( 'removed '.$count.' files from index' );
 	}
}
