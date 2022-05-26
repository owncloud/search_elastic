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
		<select id="authType">
			<option value="none">No Authentication</option>
			<option value="userPass">Username and Password</option>
			<option value="apiKey">API Key</option>
		</select>
	</div>
	<div id="authParams" class="field-margin-left-16">
		<div id="userPassAuthParams" class="hide">
			<div>
				<input name="username" type="text" placeholder="User">
			</div>
			<div>
				<input name="password" type="password" placeholder="Password">
			</div>
		</div>
		<div id="apiKeyAuthParams" class="hide">
			<div>
				<input name="apiKey" type="password" placeholder="API Key">
			</div>
		</div>
	</div>
	<div class="field-margin-left-16">
		<button id="saveConfiguration"><?php p($l->t('Save Configuration'));?></button>
		<button id="rescan"><?php p($l->t('Rescan'));?></button>
		<label><input type="checkbox" /> <?php p($l->t('Scan external storages'));?></label>
		<br/><span class="message"></span>
	</div>
</div>
