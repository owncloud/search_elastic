<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
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
