<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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
//add 3rdparty composer autoloader
require_once __DIR__ . '/3rdparty/autoload.php';

$client = new Elastica\Client([
	'host' => \getenv('ES_HOST') ?: 'localhost',
	'port' => \getenv('ES_PORT') ?: 9200,
]);

$index = $client->getIndex('elastica_test');
$index->create(['index' => ['number_of_shards' => 1, 'number_of_replicas' => 0],], true);

$type = new Elastica\Type($index, 'test');
$type->setMapping([
	'file' => [
		'type' => 'attachment',
		'path' => 'full',
		'fields' => [
			'file' => [
				'type' => 'string',
				'term_vector' => 'with_positions_offsets',
				'store' => 'yes',
			]
		]
	]
]);

$doc1 = new Elastica\Document(1);
$doc1->addFile('file', __DIR__ . '/tests/unit/data/libreoffice/document split - a1.pdf', 'application/pdf');
$type->addDocument($doc1);

$index->optimize();

$es_match = new Elastica\Query\Match();
$es_match->setField("file", "term1");

$es_query = new Elastica\Query($es_match);
$es_query->setFields(['file']);
$es_query->setHighlight([
	/*'pre_tags' => array('<em class="highlight">'),
	'post_tags' => array('</em>'),*/
	'fields' => [
		'file' => new stdClass,
	],
]);

$q = $es_query->toArray();
echo \json_encode($q, JSON_PRETTY_PRINT);

$search = new Elastica\Search($client);
$resultSet = $search->search($es_query, 10);
$elasticaResults  = $resultSet->getResults();

foreach ($elasticaResults as $elasticaResult) {
	\var_dump($elasticaResult->getHighlights());
	\var_dump($elasticaResult->getData());
}
