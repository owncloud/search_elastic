(function(){

	$(document).ready(function() {
		var $searchElasticSettings = $('#searchElasticSettings');

		function loadServers($element) {
			return $.get(
				OC.generateUrl('apps/search_elastic/settings/servers')
			).done(function( result ) {
				var host = result.servers;
				var authType = result.server_auth.auth;

				if (authType === '') {
					authType = 'none';
				}

				$element.find('#host').val(host);
				$element.find('#authType').val(authType);
				showSelectedAutenticationSettings(authType);

				var authParams = result.server_auth.authParams;
				var $authParamsElem = $element.find('#' + getTargetAuthParamsDivId(authType));
				$.each(authParams, function(key, value) {
					$authParamsElem.find('input[name="' + key + '"]').val(value);
				});
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not load servers'));
			});
		}
		function saveServers(servers, authType, authParams) {
			return $.post(
				OC.generateUrl('apps/search_elastic/settings/servers'),
				{ servers: servers, authType: authType, authParams: authParams }
			).done(function() {
				var authTypeDivId = getTargetAuthParamsDivId(authType);
				// clear all the other inputs from other authTypes
				$('#authParams > div:not(#' + authTypeDivId + ') input').val('');
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not save servers'));
			});
		}
		function getScanExternalStorages($element) {
			return $.get(
				OC.generateUrl('apps/search_elastic/settings/scanExternalStorages')
			).done(function( result ) {
				$element.prop('checked', result.scanExternalStorages);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not get scanExternalStorages'));
			});
		}
		function toggleScanExternalStorages($element) {
			return $.post(
				OC.generateUrl('apps/search_elastic/settings/scanExternalStorages'),
				{ scanExternalStorages: $element.prop('checked') }
			).done(function( result ) {
				$element.prop('checked', result.scanExternalStorages);
			}).fail(function( result ) {
				$element.prop('checked', !$element.prop('checked'));
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not set scanExternalStorages'));
			});
		}
		function renderStatus(stats) {
			console.debug(stats);
			var count = stats.oc_index.total.docs.count;
			var size_in_bytes = stats.oc_index.total.store.size_in_bytes;
			var countIndexed = stats.countIndexed;
			$searchElasticSettings.find('.message').text(
				n('search_elastic', '{countIndexed} nodes marked as indexed, {count} document in index uses {size} bytes', '{countIndexed} nodes marked as indexed, {count} documents in index using {size} bytes', count, {count: count, countIndexed:countIndexed, size: size_in_bytes})
			);
		}
		function checkStatus() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			return $.get(
				OC.generateUrl('apps/search_elastic/settings/status')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$('#rescan').text(t('search_elastic', 'Reset index'));
				renderStatus(result.stats);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				$searchElasticSettings.find('.message').text(result.responseJSON.message);
				$('#rescan').text(t('search_elastic', 'Setup index'));
			});
		}
		function setup() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			return $.post(
				OC.generateUrl('apps/search_elastic/setup')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$('#rescan').text(t('search_elastic', 'Reset index'));
				renderStatus(result.stats);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				console.error(result);
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not setup indexes'));
			});
		}

		$('.select2').select2({
			minimumResultsForSearch: -1
		});
		$('#saveConnectors').on('click', function(e) {
			var sCon = $('#searchConnector').val();
			var wCon = $('#writeConnectorList').val();
			if (wCon.indexOf(sCon) !== -1) {
				// search connector must be present in the write connectors
				$.post(
					OC.generateUrl('apps/search_elastic/settings/connectors/save'),
					{
						searchConnector: sCon,
						writeConnectors: wCon
					}
				).always(function(result) {
					OC.msg.finishedSaving('#saveMessage', result);
				});
			} else {
				OC.msg.finishedError('#saveMessage', t('search_elastic', 'Error: the search connector must be present in the write connectors'));
			}
		});

		$('#saveConfiguration').on('click', function(e) {
			var host = $('#host').val();
			var auth = $('#authType').val();
			var authParams = {};

			$('#' + getTargetAuthParamsDivId(auth) + ' input').each(function () {
				var $currentElement = $(this);
				authParams[$currentElement.attr('name')] = $currentElement.val();
			});
			saveServers(host, auth, authParams)
			.done(function() {
				checkStatus();
			});
		});

		$('#rescan').on('click', function(e) {
			setup();
		});

		$searchElasticSettings.on('click', 'input[type="checkbox"]', function(e) {
			toggleScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		});

		$('#authType').change(function(){
			showSelectedAutenticationSettings($(this).val());
		});

		function getTargetAuthParamsDivId(authOption) {
			return authOption + 'AuthParams';
		}

		function showSelectedAutenticationSettings(option) {
			$('#authParams > div').hide();
			$('#authParams > div#' + getTargetAuthParamsDivId(option)).show();
		}

		loadServers($searchElasticSettings);
		getScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		checkStatus();

	});

})();
