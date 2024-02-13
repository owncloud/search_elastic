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
				<input name="apiKey" type="password" placeholder="Encoded API Key">
			</div>
		</div>
	</div>
	<div class="field-margin-left-16">
		<button id="saveConfiguration"><?php p($l->t('Save Configuration'));?></button>
		<button id="rescan"><?php p($l->t('Rescan'));?></button>
		<label><input type="checkbox" /> <?php p($l->t('Scan external storages'));?></label>
		<br/><span class="message"></span>
	</div>
	<div class="field-margin-left-16">
		<h3><?php p($l->t('Connector setup')); ?></h3>
		<div>
			<p><?php p($l->t('Configure the connectors that will be used to write and to search in elasticsearch')); ?></p>
			<p><?php p($l->t('Usually, only one write connector needs to be used, and the search connector must be the same')); ?></p>
			<p><?php p($l->t('For data migration purposes, a second or even a third connector can be used, so new data is written in all the selected connectors until the data migration is finished')); ?></p>
			<p><?php p($l->t('Once the data is fully migrated, you can switch the search connector and remove the old connector from the list of write connectors')); ?></p>
			<p><?php p($l->t('IMPORTANT: The selected search connector must be present in the selected write connectors')); ?></p>
		</div>
		<div>
			<label for="writeConnectorList"><?php p($l->t('Write Connectors')); ?></label>
			<select id="writeConnectorList" class="select2" multiple="multiple" style="width:250px">
				<?php foreach ($_['connectorList'] as $connectorName): ?>
					<?php if (\in_array($connectorName, $_['writeConnectors'], true)): ?>
					<option value="<?php p($connectorName); ?>" selected="selected"><?php p($connectorName); ?></option>
					<?php else: ?>
					<option value="<?php p($connectorName); ?>"><?php p($connectorName); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label for="searchConnector"><?php p($l->t('Search Connector')); ?></label>
			<select id="searchConnector" class="select2" style="width:250px">
				<?php foreach ($_['connectorList'] as $connectorName): ?>
					<?php if ($connectorName === $_['searchConnector']): ?>
					<option value="<?php p($connectorName); ?>" selected="selected"><?php p($connectorName); ?></option>
					<?php else: ?>
					<option value="<?php p($connectorName); ?>"><?php p($connectorName); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<button id="saveConnectors"><?php p($l->t('Save Connectors'));?></button>
			<span id="saveMessage" class="msg"></span>
		</div>
	</div>
</div>
