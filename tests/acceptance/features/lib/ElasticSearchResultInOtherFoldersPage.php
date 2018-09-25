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

namespace Page;

use Behat\Mink\Session;
use Page\FilesPageElement\FileRow;


/**
 * Page that shows the search results from other folders
 * in the case of an elastic search.
 */
class ElasticSearchResultInOtherFoldersPage extends SearchResultInOtherFoldersPage {

	/**
	 * get the highlighted content of the file with a given name and path
	 *
	 * @param Session $session
	 * @param string $fileName
	 * @param string $path
	 *
	 * @return string
	 */
	public function getHighlightsText(Session $session, $fileName, $path) {
		/**
		 *
		 * @var FileRow $fileRow
		 */
		$fileRow = $this->findFileRowByNameAndPath(
			$fileName, $path, $session
		);
		return $fileRow->getHighlightsElement()->getText();
	}
}
