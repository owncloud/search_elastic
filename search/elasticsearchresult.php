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

namespace OCA\Search_Elastic\Search;

use Elastica\Result;
use OC\Files\Filesystem;
use OC\Search\Result\File;

/**
 * A found file
 */
class ElasticSearchResult extends File {

	/**
	 * Type name; translated in templates
	 * @var string
	 */
	public $type = 'search_elastic';

	/**
	 * @var float
	 */
	public $score;

	/**
	 * Create a new content search result
	 * @param Result $result file data given by provider
	 */
	public function __construct(Result $result) {
		$data = $result->getData();
		$highlights = $result->getHighlights();
		$this->id = (int)$result->getId();
		$this->path = $this->getRelativePath($data['file']['_name']);
		$this->name = basename($this->path);
		$this->size = (int)$data['file']['content_length'];
		$this->score = $result->getScore();
		$this->link = \OCP\Util::linkTo(
			'files',
			'index.php',
			array('dir' => dirname($this->path), 'file' => $this->name)
		);
		$this->permissions = $this->getPermissions($this->path);
		$this->modified = (int)$data['file']['mtime'];
		$this->mime_type = $data['file']['content_type'];
		$this->highlights = $highlights['content'];
	}

	//FIXME resolve path for shared files
	protected function getRelativePath ($path) {
		$root = \OC::$server->getUserFolder();
		return $root->getRelativePath($path);
  	}

	/**
	 * Determine permissions for a given file path
	 * @param string $path
	 * @return int
	 */
	function getPermissions($path) {
		// add read permissions
		$permissions = \OCP\PERMISSION_READ;
		// get directory
		$fileInfo = pathinfo($path);
		$dir = $fileInfo['dirname'] . '/';
		// add update permissions
		if (Filesystem::isUpdatable($dir)) {
			$permissions |= \OCP\PERMISSION_UPDATE;
		}
		// add delete permissions
		if (Filesystem::isDeletable($dir)) {
			$permissions |= \OCP\PERMISSION_DELETE;
		}
		// add share permissions
		if (Filesystem::isSharable($dir)) {
			$permissions |= \OCP\PERMISSION_SHARE;
		}
		// return
		return $permissions;
	}

}
