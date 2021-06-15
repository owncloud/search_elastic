<?php
/**
 * @author Artur Neumann <info@individual-it.net>
 * @author Michael Barz <mbarz@owncloud.com>
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
	 * original app settings before the test-run
	 *
	 * @var null|string|array
	 * null means the setting was not changed
	 * empty array or empty string means the setting was not set
	 * and need to be deleted
	 */
	private $originalNoContentSetting = null;
	private $originalGroupLimitSetting = null;
	private $originalGroupNoContentSetting = null;

	/**
	 * @Given the search index has been created
	 * @Given the search index of user :user has been created
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function createIndex($user = null) {
		if ($user === null) {
			$user = '--all';
		}
		SetupHelper::runOcc(["search:index:create", $user]);
		SetupHelper::resetOpcache(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword()
		);
	}

	/**
	 * @Given the search index has been reset
	 *
	 * @return void
	 */
	public function resetIndex() {
		SetupHelper::runOcc(["search:index:reset --force"]);
		SetupHelper::resetOpcache(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword()
		);
	}

	/**
	 * @Given the search index has been updated
	 *
	 * @return void
	 */
	public function updateIndex() {
		SetupHelper::runOcc(["search:index:update"]);
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
				"search_elastic",
				"nocontent"
			)['value'];
		}
		AppConfigHelper::modifyAppConfig(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			"search_elastic",
			"nocontent",
			"true"
		);
	}

	/**
	 * @When the administrator limits the access to search_elastic to :group
	 * @Given the administrator has limited the access to search_elastic to :group
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function limitAccessTo($group) {
		if ($this->originalGroupLimitSetting === null) {
			$this->originalGroupLimitSetting = AppConfigHelper::getAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic",
				"group"
			)['value'];
		}
		AppConfigHelper::modifyAppConfig(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			"search_elastic",
			"group",
			$group
		);
	}

	/**
	 * @When the administrator disables the full text search for :group
	 * @Given the administrator has disabled the full text search for :group
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function disableFullTextSearchFor($group) {
		if ($this->originalGroupNoContentSetting === null) {
			$this->originalGroupNoContentSetting = AppConfigHelper::getAppConfig(
				$this->featureContext->getBaseUrl(),
				$this->featureContext->getAdminUsername(),
				$this->featureContext->getAdminPassword(),
				"search_elastic",
				"group.nocontent"
			)['value'];
		}
		AppConfigHelper::modifyAppConfig(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			"search_elastic",
			"group.nocontent",
			$group
		);
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
		$settings = [
			"nocontent" => $this->originalNoContentSetting,
			"group" => $this->originalGroupLimitSetting,
			"group.nocontent" => $this->originalGroupNoContentSetting
		];
		foreach ($settings as $configKey => $originalValue) {
			if ($originalValue === ""
				|| (\is_array($originalValue)
				&& \count($originalValue) === 0)
			) {
				AppConfigHelper::deleteAppConfig(
					$this->featureContext->getBaseUrl(),
					$this->featureContext->getAdminUsername(),
					$this->featureContext->getAdminPassword(),
					"search_elastic",
					$configKey
				);
			} elseif ($originalValue !== null) {
				AppConfigHelper::modifyAppConfig(
					$this->featureContext->getBaseUrl(),
					$this->featureContext->getAdminUsername(),
					$this->featureContext->getAdminPassword(),
					"search_elastic",
					$configKey,
					$originalValue
				);
			}
		}
		$this->resetIndex();
	}
}
