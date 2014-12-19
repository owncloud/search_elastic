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

use Elastica\Query;
use Elastica\Query\Bool;
use Elastica\Result;
use Elastica\Client;
use Elastica\Type;
use OCA\Search_Elastic\AppInfo\Application;
use OCP\Search\PagedProvider;

class ElasticSearchProvider extends PagedProvider {

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

		$results=array();
		if ( $query !== null ) {
			try {
				/** @var Client $client */
				$client = $container->query('Elastica');
				$index = $container->query('Index');

				$es_matchUser = new \Elastica\Query\Match();
				$es_matchUser->setField('file.users', $container->getServer()->getUserSession()->getUser()->getUID());

				$es_match = new \Elastica\Query\Match();
				$es_match->setField('content', $query);

				$es_bool = new Bool();
				$es_bool->addMust($es_matchUser);
				$es_bool->addMust($es_match);

				$es_query = new Query($es_bool);
				$es_query->setHighlight(array(
					'fields' => array(
						'content' => new \stdClass,
					),
				));

				$es_query->setSize($size);
				$es_query->setFrom(($page - 1) * $size);

				$search = new \Elastica\Search($client);
				$search->addType(new Type($index, 'file'));
				$resultSet = $search->search($es_query);

				/** @var Result $result */
				foreach ($resultSet as $result) {
					$results[] = new ElasticSearchResult($result);
				}

			} catch ( \Exception $e ) {
				$container->query('Logger')->error( $e->getMessage().' Trace:\n'.$e->getTraceAsString() );
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

}