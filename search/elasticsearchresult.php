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

namespace OCA\Search_Elastic\Search;

use Elastica\Result;
use OC\Search\Result\File as FileResult;
use OCP\Files\Folder;
use OCP\Files\Node;

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
	 * @var string[]
	 */
	public $highlights;

	/**
	 * Create a new content search result
	 * @param Result $result file data given by provider
	 * @param Node $node
	 * @param Folder $home
	 */
	public function __construct(Result $result, Node $node, Folder $home) {
		$data = $result->getData();
		$highlights = $result->getHighlights();
		$this->id = (int)$result->getId();
		$this->path = $home->getRelativePath($node->getPath());
		$this->name = \basename($this->path);
		$this->size = (int)$node->getSize();
		$this->score = $result->getScore();
		$this->mime_type = $node->getMimetype();
		if ($this->mime_type === 'httpd/unix-directory') {
			$this->link = \OC::$server->getURLGenerator()->linkToRoute(
				'files.view.index',
				[
					'dir' => $this->path,
				]
			);
		} else {
			$this->link = \OC::$server->getURLGenerator()->linkToRoute(
				'files.view.index',
				[
					'dir' => \dirname($this->path),
					'scrollto' => $this->name,
				]
			);
		}
		$this->permissions = $node->getPermissions();
		$this->modified = (int)$data['mtime'];
		if (isset($highlights['file.content'])) {
			$this->highlights = $highlights['file.content'];
		} else {
			$this->highlights = [];
		}
	}
}
