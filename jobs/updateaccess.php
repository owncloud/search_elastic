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

namespace OCA\Search_Elastic\Jobs;

use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Client;
use OC\BackgroundJob\QueuedJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\ILogger;

class UpdateAccess extends QueuedJob {

	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var Client
	 */
	private $client;

	private $userId;
	/**
	 * updates the users ad groups that have access to a file or folder
	 * @param array $arguments
	 */
	public function run($arguments){
		$app = new Application();
		$container = $app->getContainer();

		$this->logger = \OC::$server->getLogger();

		if (isset($arguments['userId']) && $arguments['nodeId']) {
			$this->userId = $arguments['userId'];

			$home = \OC::$server->getUserFolder($arguments['userId']);

			if ($home) {

				$this->client = $container->query('Client');

				$nodes = $home->getById($arguments['nodeId']);

				//we only need one node
				$this->updateNode($nodes[0]);
			}
		} else {
			$this->logger->debug(
				'indexer job did not receive userId or nodeId in arguments: '.json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
 	}

	public function updateNode(Node $node) {
		if ($node instanceof Folder) {
			$children = $node->getDirectoryListing();
			//traverse children
			foreach ($children as $child) {
				$this->updateNode($child);
			}
		} else if ($node instanceof File) {
			$this->updateFile($node);
		} else {
			$this->logger->warning('Expected File or Folder instance, got '.
				json_encode($node), ['app' => 'search_elastic']);
		}
	}

	public function updateFile(File $file) {
		$this->logger->debug('background job updating '.$file->getPath()
			. '('.$file->getId().')', ['app' => 'search_elastic'] );
		$this->client->updateFile($file, $this->userId);
	}

}
