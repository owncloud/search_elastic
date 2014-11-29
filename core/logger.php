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

namespace OCA\Search_Elastic\Core;

use OCP\ILogger;
use OC\Log;

/**
 * Class Logger
 *
 * inserts the app name when not set in context
 */
class Logger extends Log {

	private $appName;
	private $logger;

	public function __construct($appName, ILogger $logger) {
		$this->appName = $appName;
		$this->logger = $logger;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = array()) {
		if (empty($context['app'])) {
			$context['app'] = $this->appName;
		}
		$this->logger->log($level, $message, $context);
	}
}

