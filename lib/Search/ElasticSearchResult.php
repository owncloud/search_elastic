<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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

namespace OCA\Search_Elastic\Search;

use Elastica\Result;
use OCA\Search_Elastic\Connectors\IConnector;
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
	public function __construct(Result $result, IConnector $connector, Node $node, Folder $home) {
		parent::__construct($node);
		$this->id = $connector->findInResult($result, 'id');
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
		$this->permissions = (string) $node->getPermissions();
		$this->modified = $connector->findInResult($result, 'mtime');
		$this->highlights = $connector->findInResult($result, 'highlights');
	}
}
