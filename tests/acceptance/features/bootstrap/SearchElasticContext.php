<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <info@jankaritech.com>
 * @copyright Copyright (c) 2018 Artur Neumann info@jankaritech.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use TestHelpers\SetupHelper;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use TestHelpers\AppConfigHelper;

require_once 'bootstrap.php';

/**
 * Context for search elastic specific steps
 */
class SearchElasticContext implements Context {

	/**
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * app setting of search_elastic nocontent before the test-run
	 *
	 * @var null|string|array
	 * null means the setting was not changed
	 * empty array or empty string means the setting was not set
	 * and need to be deleted
	 */
	private $originalNoContentSetting = null;

	/**
	 * @Given all files have been indexed
	 * @Given files of user :user have been indexed
	 * @When the administrator indexes all files
	 * @When the administrator indexes files of user :user
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function indexFiles($user = null) {
		if ($user === null) {
			$user = '--all';
		}
		SetupHelper::runOcc(["search:index", $user]);
		SetupHelper::resetOpcache(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword()
		);
	}

	/**
	 * @Given the search index has been reset
	 * @When the administrator resets the search index
	 *
	 * @return void
	 */
	public function resetIndex() {
		SetupHelper::runOcc(["search:reset"]);
		SetupHelper::resetOpcache(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword()
		);
	}

	/**
	 * @Given the administrator has configured the search_elastic app to index only metadata
	 *
	 * @return void
	 */
	public function setAppToIndexOnlyMetadata() {
		if ($this->originalNoContentSetting === null) {
			$this->originalNoContentSetting = AppConfigHelper::getAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic", "nocontent"
			)['value'];
			AppConfigHelper::modifyAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic", "nocontent", "true"
			);
		}
	}

	/**
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 * @throws Exception
	 */
	public function setUpScenario(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		SetupHelper::init(
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
		$this->resetIndex();
	}

	/**
	 * @AfterScenario
	 *
	 * @param AfterScenarioScope $scope
	 *
	 * @return void
	 */
	public function tearDownScenario(AfterScenarioScope $scope) {
		if ($this->originalNoContentSetting === ""
			|| (\is_array($this->originalNoContentSetting)
			&& \count($this->originalNoContentSetting) === 0)
		) {
			AppConfigHelper::deleteAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic", "nocontent"
			);
		} elseif ($this->originalNoContentSetting !== null) {
			AppConfigHelper::modifyAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic", "nocontent", $this->originalNoContentSetting
			);
		}
		$this->resetIndex();
	}
}
