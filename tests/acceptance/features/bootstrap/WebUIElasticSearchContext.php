<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2018 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\ElasticSearchResultInOtherFoldersPage;
use Page\SearchResultInOtherFoldersPage;
use Page\OwncloudPage;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\Assert;

require_once 'bootstrap.php';

/**
 * WebUI Elastic Search context.
 */
class WebUIElasticSearchContext extends RawMinkContext implements Context {
	/**
	 *
	 * @var SearchResultInOtherFoldersPage
	 */
	private $searchResultInOtherFoldersPage;
	/**
	 *
	 * @var OwncloudPage
	 */
	private $ownCloudPage;

	/**
	 *
	 * @var WebUIGeneralContext
	 */
	private $webUIGeneralContext;

	/**
	 *
	 * @var WebUIFilesContext
	 */
	private $webUIFilesContext;

	/**
	 * WebUIElasticSearchContext constructor.
	 *
	 * @param OwncloudPage $ownCloudPage
	 * @param ElasticSearchResultInOtherFoldersPage $searchResultInOtherFoldersPage
	 */
	public function __construct(
		OwncloudPage $ownCloudPage,
		ElasticSearchResultInOtherFoldersPage $searchResultInOtherFoldersPage
	) {
		$this->ownCloudPage = $ownCloudPage;
		$this->searchResultInOtherFoldersPage = $searchResultInOtherFoldersPage;
	}

	/**
	 * @Then /^(?:file|folder) ((?:'[^']*')|(?:"[^"]*")) with path ((?:'[^']*')|(?:"[^"]*")) should be listed in the search results in the other folders section on the webUI with highlights containing:$/
	 *
	 * @param string $fileName
	 * @param string $path
	 * @param PyStringNode $highlightsExpectations
	 *
	 * @return void
	 */
	public function fileShouldBeListedSearchResultOtherFoldersWithHighlights(
		$fileName, $path, PyStringNode $highlightsExpectations
	) {
		$fileName = \trim($fileName, $fileName[0]);
		$path = \trim($path, $path[0]);
		$this->webUIGeneralContext->setCurrentPageObject(
			$this->searchResultInOtherFoldersPage
		);
		$this->webUIFilesContext->checkIfFileFolderIsListedOnTheWebUI(
			$fileName, "should", "search results page", "", $path
		);
		$highlights = $this->searchResultInOtherFoldersPage->getHighlightsText(
			$this->getSession(), $fileName, $path
		);
		Assert::assertContains(
			$highlightsExpectations->getRaw(), $highlights
		);
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario @webUI
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function before(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->webUIGeneralContext = $environment->getContext('WebUIGeneralContext');
		$this->webUIFilesContext = $environment->getContext('WebUIFilesContext');
	}
}
