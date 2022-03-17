<?php
script('search_elastic', 'settings/admin');
style('search_elastic', 'settings/admin');
?>
<div class="section" id="searchElasticSettings" >

	<h2 class="appname"><?php p($l->t('Elasticsearch'));?></h2>
	<div>
		<span class="icon"></span>
		<input id="host" type="text" placeholder="localhost:9200" />
	</div>
	<div class="field-margin-left-16">
		<select id="authenticationSettings">
			<option value="none">No Authentication</option>
			<option value="userPassOption">User and Password</option>
		</select>
	</div>
	<div id="userPassSettings" class="field-margin-left-16">
		<div>
			<input id="user" type="text" placeholder="User">
		</div>
		<div>
			<input id="password" type="password" placeholder="Password">
		</div>
	</div>
	<div class="field-margin-left-16">
		<button id="saveConfiguration"><?php p($l->t('Save Configuration'));?></button>
		<button id="rescan"><?php p($l->t('Rescan'));?></button>
		<label><input type="checkbox" /> <?php p($l->t('Scan external storages'));?></label>
		<br/><span class="message"></span>
	</div>
</div>
