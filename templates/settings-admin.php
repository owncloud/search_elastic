<div class="section" id="searchElasticSettings" >

	<h2><?php p($l->t('Elasticsearch'));?></h2>
	<div>
		<span class="icon"></span>

		<input type="text" placeholder="localhost:9200,otherhost:9201" />

		<button><?php p($l->t('Rescan'));?></button>

		<span class="status"></span>

	</div>

	TODO status icon, red=server not found, yellow=index missing, index broken?, green=ok<br>
	TODO input for comma delimited ip:port servers<br>
	TODO rescan button that recreates the index

</div>
