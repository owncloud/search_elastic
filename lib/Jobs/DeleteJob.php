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

use OC\BackgroundJob\TimedJob;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\SearchElasticService;
use OCP\ILogger;

/**
 * Class DeleteJob
 *
 * @package OCA\Search_Elastic\Jobs
 */
class DeleteJob extends TimedJob {
	/**
	 * @var SearchElasticService
	 */
	private $searchElasticService;
	/**
	 * @var StatusMapper
	 */
	private $statusMapper;
	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * DeleteJob constructor.
	 *
	 * @param ILogger $logger
	 * @param SearchElasticService $searchElasticService
	 * @param StatusMapper $statusMapper
	 */
	public function __construct(
		ILogger $logger,
		SearchElasticService $searchElasticService,
		StatusMapper $statusMapper
	) {
		$this->logger = $logger;
		$this->searchElasticService = $searchElasticService;
		$this->statusMapper = $statusMapper;
		//execute once a minute
		$this->setInterval(60);
	}

	/**
	 * @param array $arguments
	 *
	 * @return void
	 */
	public function run($arguments) {
		$this->logger->debug('removing deleted files', ['app' => 'search_elastic']);
		$deletedIds = $this->statusMapper->getDeleted();

		if (!empty($deletedIds)) {
			$this->logger->debug(
				\count($deletedIds) . ' fileids need to be removed:' .
				'( ' . \implode(';', $deletedIds) . ' )',
				['app' => 'search_elastic']
			);

			//delete from status table
			$deletedInDb = $this->statusMapper->deleteIds($deletedIds);
			$deletedInIndex = $this->searchElasticService->deleteFiles($deletedIds);
			$this->logger->debug(
				"removed $deletedInDb ids from status table and $deletedInIndex documents from index",
				['app' => 'search_elastic']
			);
		}
	}
}
