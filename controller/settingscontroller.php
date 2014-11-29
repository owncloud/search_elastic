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

namespace OCA\Search_Elastic\Controller;

use OCA\Search_Elastic\Client;
use OCP\IRequest;
use OCP\AppFramework\Controller;

class SettingsController extends Controller {

	/**
	 * @var $index Client
	 */
	private $index;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param Client $client
	 */
	public function __construct($appName, IRequest $request, Client $client) {
		parent::__construct($appName, $request);
		$this->client = $client;
	}

	/**
	 * setup the index
	 */
	public function setup() {
	}

}