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

use OCP\Files\Folder;
use OCP\IUserManager;
use OCP\IUserSession;

class Files {

	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var Folder
	 */
	private $rootFolder;

	public function __construct(IUserManager $userManager, IUserSession $userSession, Folder $rootFolder){
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
	}
	/**
	 * @param string $userId
	 * @return Folder
	 * @throws SetUpException
	 */
	public function setUpUserHome($userId = null) {

		if (is_null($userId)) {
			$user = $this->userSession->getUser();
		} else {
			$user = $this->userManager->get($userId);
		}
		if (is_null($user) || !$this->userManager->userExists($user->getUID())) {
			throw new SetUpException('could not set up user home for '.json_encode($user));
		}
		if ($user !== $this->userSession->getUser()) {
			\OC_Util::tearDownFS();
			$this->userSession->setUser($user);
		}
		\OC_Util::setupFS($user->getUID());

		return $this->rootFolder->get('/' . $user->getUID());

	}

}
