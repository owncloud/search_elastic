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

namespace OCA\Search_Elastic\Jobs;

use OC\BackgroundJob\QueuedJob;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Crypto\Encryption;
use OCA\Encryption\Session;
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Db\StatusMapper;
use OCP\AppFramework\IAppContainer;
use OCP\Files\Folder;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use Sabre\DAV\Exception\NotImplemented;

class UpdateContent extends QueuedJob implements IUserSession {

	/**
	 * @var ILogger
	 */
	protected $logger;

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var IUser
	 */
	protected $user;

	/**
	 * @var IAppContainer
	 */
	protected $container;

	/**
	 * updates changed content for files
	 * @param array $arguments
	 */
	public function run($arguments){
		$app = new Application();
		$this->container = $app->getContainer();
		$this->logger = \OC::$server->getLogger();
		$this->config = \OC::$server->getConfig();

		if (isset($arguments['userId'])) {
			$userId = $arguments['userId'];

			// fake user for encryption
			$this->user = \OC::$server->getUserManager()->get($userId);
			\OC::$server->getUserSession()->setUser($this->user);

			// This sets up the correct storage.
			// The db mapper does some magic with the filesystem
			$this->initMasterKeyIfAvailable();
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($userId);
			$home = \OC::$server->getUserFolder($userId);

			if ($home instanceof Folder) {

				/** @var StatusMapper $statusMapper */
				$statusMapper = $this->container->query('StatusMapper');
				$fileIds = $statusMapper->findFilesWhereContentChanged($home);

				$this->logger->debug(
					count($fileIds)." files of $userId need content indexing",
					['app' => 'search_elastic']
				);

				$this->container->query('SearchElasticService')->indexNodes($userId, $fileIds);

			} else {
				$this->logger->debug(
					'could not resolve user home: '.json_encode($arguments),
					['app' => 'search_elastic']
				);
			}
		} else {
			$this->logger->debug(
				'did not receive userId in arguments: '.json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
 	}

	/**
	 * init master key, parts taken from the encryption app
	 *
	 * @throws \Exception
	 */
	protected function initMasterKeyIfAvailable() {

		if (\OC::$server->getAppManager()->isEnabledForUser('encryption')
			&& $this->config->getAppValue('encryption', 'useMasterKey')) {

			$masterKeyId = $this->config->getAppValue('encryption', 'masterKeyId');
			$passPhrase = $this->config->getSystemValue('secret');
			$privateKey = \OC::$server->getEncryptionKeyStorage()->getSystemUserKey($masterKeyId . '.privateKey', Encryption::ID);

			$crypt = new Crypt($this->logger, $this, $this->config);

			$privateKey = $crypt->decryptPrivateKey($privateKey, $passPhrase, $masterKeyId);
			if ($privateKey) {
				\OC::$server->getUserSession()->getSession()->set('privateKey', $privateKey);
				\OC::$server->getUserSession()->getSession()->set('encryptionInitialized', Session::INIT_SUCCESSFUL);
			} else {
				// TODO log errors
			}
		}
	}


	// ---- needed to implement the IUserSession interface,
	// ---- we only use it to get an instance of the Crypt class because it
	// ---- extracts the user id from the UserSession
	/**
	 * set the currently active user
	 *
	 * @param \OCP\IUser|null $user
	 * @since 8.0.0
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * get the current active user
	 *
	 * @return \OCP\IUser|null Current user, otherwise null
	 * @since 8.0.0
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Checks whether the user is logged in
	 *
	 * @return bool if logged in
	 * @since 8.0.0
	 */
	public function isLoggedIn() {
		return true;
	}

	/**
	 * Do a user login
	 *
	 * @param string $user the username
	 * @param string $password the password
	 * @return bool true if successful
	 * @throws NotImplemented
	 * @since 6.0.0
	 */
	public function login($user, $password) {
		throw new NotImplemented();
	}

	/**
	 * Logs the user out including all the session data
	 * Logout, destroys session
	 *
	 * @return void
	 * @since 6.0.0
	 * @throws NotImplemented
	 */
	public function logout() {
		throw new NotImplemented();
	}
}
