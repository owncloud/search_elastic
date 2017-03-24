<?php
script('search_elastic', 'settings/admin');
style('search_elastic', 'settings/admin');
?>
<div class="section" id="searchElasticSettings" >

	<h2 class="appname"><?php p($l->t('Elasticsearch'));?></h2>
	<div>
		<span class="icon"></span>

		<input type="text" placeholder="localhost:9200,otherhost:9201" />

		<button><?php p($l->t('Rescan'));?></button>

		<label><input type="checkbox" /> <?php p($l->t('Scan external storages'));?></label>

		<br/><span class="message"></span>

	</div>

</div>
