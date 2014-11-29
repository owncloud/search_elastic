<?php

//add 3rdparty composer autoloader
require_once __DIR__ . '/3rdparty/autoload.php';


$client = new Elastica\Client(array(
	'host' => getenv('ES_HOST') ?: 'localhost',
	'port' => getenv('ES_PORT') ?: 9200,
));

$index = $client->getIndex('elastica_test');
$index->create(array('index' => array('number_of_shards' => 1, 'number_of_replicas' => 0),), true);

$type = new Elastica\Type($index, 'test');
$type->setMapping(array(
	'file' => array(
		'type' => 'attachment',
		'path' => 'full',
		'fields' => array(
			'file' => array(
				'type' => 'string',
				'term_vector' => 'with_positions_offsets',
				'store' => 'yes',
			)
		)
	)
));

$doc1 = new Elastica\Document(1);
$doc1->addFile('file', __DIR__ . '/tests/unit/data/libreoffice/document split - a1.pdf', 'application/pdf');
$type->addDocument($doc1);

$index->optimize();

$es_match = new Elastica\Query\Match();
$es_match->setField("file", "term1");

$es_query = new Elastica\Query($es_match);
$es_query->setFields(array('file'));
$es_query->setHighlight(array(
	/*'pre_tags' => array('<em class="highlight">'),
	'post_tags' => array('</em>'),*/
	'fields' => array(
		'file' => new stdClass,
	),
));

$q = $es_query->toArray();
echo json_encode($q, JSON_PRETTY_PRINT);

$search = new Elastica\Search($client);
$resultSet = $search->search($es_query, 10);
$elasticaResults  = $resultSet->getResults();

foreach ($elasticaResults as $elasticaResult) {
	var_dump($elasticaResult->getHighlights());
	var_dump($elasticaResult->getData());
}