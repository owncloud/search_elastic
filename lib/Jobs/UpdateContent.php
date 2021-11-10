<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Saugat Pachhai <suagatchhetri@outlook.com>
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

use OC\BackgroundJob\QueuedJob;
use OCA\Encryption\Crypto\Encryption;
use OCA\Encryption\KeyManager;
use OCA\Search_Elastic\AppInfo\Application;
use OCA\Search_Elastic\Db\StatusMapper;
use OCA\Search_Elastic\SearchElasticService;
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
	 *
	 * @param array $arguments
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function run($arguments) {
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
				$statusMapper = $this->container->query(StatusMapper::class);
				$fileIds = $statusMapper->findFilesWhereContentChanged($home);

				$this->logger->debug(
					\count($fileIds) . " files of $userId need content indexing",
					['app' => 'search_elastic']
				);

				$this->container->query(SearchElasticService::class)->indexNodes($userId, $fileIds);
			} else {
				$this->logger->debug(
					'could not resolve user home: ' . \json_encode($arguments),
					['app' => 'search_elastic']
				);
			}
		} else {
			$this->logger->debug(
				'did not receive userId in arguments: ' . \json_encode($arguments),
				['app' => 'search_elastic']
			);
		}
	}

	/**
	 * init master key, parts taken from the encryption app
	 *
	 * @throws \Exception
	 *
	 * @return void
	 * @suppress PhanUndeclaredClassConstant Encryption app not available in ci
	 * @suppress PhanUndeclaredClassMethod Encryption app not available in ci
	 */
	protected function initMasterKeyIfAvailable() {
		if (\OC::$server->getEncryptionManager()->isReady()
			&& \OC::$server->getAppManager()->isEnabledForUser('encryption')
			&& $this->config->getAppValue('encryption', 'useMasterKey')
		) {

			// we need to initialize a fresh app container to get the current session
			$encryption = new \OCA\Encryption\AppInfo\Application([], true);
			$encryption_manager = \OC::$server->getEncryptionManager();
			$encryption_manager->unregisterEncryptionModule(Encryption::ID);
			$encryption->registerEncryptionModule();

			// OCA\Encryption\KeyManager may not be available when running phan
			/* @phan-suppress-next-line PhanUndeclaredClassReference */
			$keyManager = $encryption->getContainer()->query(KeyManager::class);
			$keyManager->init('', ''); // uid and password are overwritten in master key mode
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
