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

use Elastica\Client;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Result;
use Elastica\Search;
use Elastica\Type;
use OCA\Search_Elastic\AppInfo\Application;
use OCP\Files\Node;
use OCP\IGroup;
use OCP\ILogger;
use OCP\IUser;
use OCP\Search\PagedProvider;

class ElasticSearchProvider extends PagedProvider {

	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var Index
	 */
	private $index;
	/**
	 * @var Client
	 */
	private $client;
	/**
	 * @var IUser
	 */
	private $user;
	/**
	 * @var IGroup[]
	 */
	private $groups;
	/**
	 * Search for $query
	 * @param string $query
	 * @param int $page pages start at page 1
	 * @param int $size, 0 = all
	 * @return ElasticSearchResult[]
	 */
	public function searchPaged($query, $page, $size) {

		$app = new Application();
		$container = $app->getContainer();

		$this->logger = \OC::$server->getLogger();
		$this->index = $container->query('Index');
		$this->client = $container->query('Elastica');
		$this->user = $container->getServer()->getUserSession()->getUser();
		$this->groups = $container->getServer()->getGroupManager()->getUserGroups($this->user);

		$results = array();
		if ( ! empty($query) ) {
			try {
				$home = \OC::$server->getUserFolder($this->user->getUID());

				do {
					$resultSet = $this->fetchResults($query, $size, $page);
					/** @var Result $result */
					foreach ($resultSet as $result) {
						$fileId = (int)$result->getId();
						$nodes = $home->getById($fileId);

						if (empty($nodes[0])) {
							$this->logger->debug("Could not find file for id $fileId in"
								. " storage {$home->getStorage()->getId()}'."
								. " Removing it from results. Maybe it was unshared"
								. " for {$this->user->getUID()}. A background job will"
								. " update the index with the new permissions.",
								['app' => 'search_elastic']);
						} else if ($nodes[0] instanceof Node) {
							$results[] = new ElasticSearchResult($result, $nodes[0], $home);
						} else {
							$this->logger->error(
								"Expected a Node for $fileId, received "
								. json_encode($nodes[0]),
								['app' => 'search_elastic']);
						}
					}
					$page++;
					// TODO We try to compensate for removed entries, but this will confuse page counting of the webui
					// Maybe add fake entries?
				} while ($resultSet->getTotalHits() === $size && count($results) < $size);

			} catch ( \Exception $e ) {
				/** @var ILogger */
				$this->logger->logException($e, ['app' => 'search_elastic']);
			}

		}
		return $results;
	}

	/**
	 * get the base type of an internet media type string
	 * 
	 * returns 'text' for 'text/plain'
	 * 
	 * @param string $mimeType internet media type
	 * @return string top-level type
	 */
	public static function baseTypeOf($mimeType) {
		return substr($mimeType, 0, strpos($mimeType, '/'));
	}

	public function fetchResults ($query, $size, $page) {

		$es_filter = new BoolQuery();
		$es_filter->addShould(new Match('file.users', $this->user->getUID()));

		$noContent = \OC::$server->getConfig()->getAppValue('search_elastic', 'nocontent', false);
		$noContentGroupCfg = \OC::$server->getConfig()->getAppValue('search_elastic', 'group.nocontent', '');
		$noContentGroups = str_getcsv($noContentGroupCfg,',','"',"\\");
		if ( $noContent === true || $noContent === 1
			|| $noContent === 'true' || $noContent === '1' || $noContent === 'on' ) {
			$searchContent = false;
		} else {
			$searchContent = true;
		}
		foreach ($this->groups as $group) {
			$groupId = $group->getGID();
			$es_filter->addShould(new Match('file.groups', $groupId));
			if (in_array($groupId, $noContentGroups)) {
				$searchContent = false;
			}
		}

		$es_bool = new BoolQuery();
		$es_bool->addFilter($es_filter);
		// wildcard queries are not analyzed, so ignore case. See http://stackoverflow.com/a/17280591
		$loweredQuery = strtolower($query);
		if ($searchContent) {
			$es_bool->addShould(new Query\MatchPhrasePrefix('file.content', $loweredQuery));
		}
		$es_bool->addShould(new Query\MatchPhrasePrefix('file.name', $loweredQuery));
		$es_bool->setMinimumShouldMatch(1);

		$es_query = new Query($es_bool);
		$es_query->setHighlight(array(
			'fields' => array(
				'file.content' => new \stdClass,
			),
		));

		$es_query->setSize($size);
		$es_query->setFrom(($page - 1) * $size);

		$search = new Search($this->client);
		$search->addType(new Type($this->index, 'file'));
		$search->addIndex($this->index);
		return $search->search($es_query);

	}
}