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
use OC\Search\Result\File as FileResult;
use OCP\Files\File;
use OCP\Files\Folder;

/**
 * A found file
 */
class ElasticSearchResult extends FileResult {

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
	public function __construct(Result $result, File $file, Folder $home) {
		$data = $result->getData();
		$highlights = $result->getHighlights();
		$this->id = (int)$result->getId();
		$this->path = $home->getRelativePath($file->getPath());
		$this->name = basename($this->path);
		$this->size = (int)$data['file']['content_length'];
		$this->score = $result->getScore();
		$this->link = \OCP\Util::linkTo(
			'files',
			'index.php',
			array('dir' => dirname($this->path), 'scrollto' => $this->name)
		);
		$this->permissions = $file->getPermissions();
		$this->modified = (int)$data['file']['mtime'];
		$this->mime = $data['file']['content_type'];
		$this->highlights = $highlights['file.content'];
	}

}
