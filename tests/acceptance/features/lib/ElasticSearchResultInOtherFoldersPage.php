<?php
/**
 * @author Artur Neumann <info@individual-it.net>
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
