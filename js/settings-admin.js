(function(){

	// TODO add spinner
	// TODO remove legend

	$(document).ready(function() {

		var $searchElasticSettings = $('#searchElasticSettings');

		function loadServers($element) {
			$.get(
				OC.generateUrl('apps/search_elastic/settings/servers')
			).done(function( result ) {
				$element.val(result.servers);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not load servers'));
			});
		}
		function saveServers(servers) {
			$.post(
				OC.generateUrl('apps/search_elastic/settings/servers'),
				{ servers: servers }
			).done(function( result ) {
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not save servers'));
			});
		}
		function checkStatus() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			$.get(
				OC.generateUrl('apps/search_elastic/settings/status')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));

				console.debug(result.stats);
				var count = result.stats.indices.owncloud.total.docs.count;
				var size_in_bytes = result.stats.indices.owncloud.total.store.size_in_bytes;
				$searchElasticSettings.find('.status').text(
					n('search_elastic', '{count} document uses {size} bytes', '{count} documents using {size} bytes', count, {count: count, size: size_in_bytes})
				);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				$searchElasticSettings.find('.status').text(result.responseJSON.message);
				$searchElasticSettings.find('button').text(t('search_elastic', 'Setup index'));
			});
		}
		function setup() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			return $.post(
				OC.generateUrl('apps/search_elastic/setup')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));

				console.debug(result.stats);
				var count = result.stats.indices.owncloud.total.docs.count;
				var size_in_bytes = result.stats.indices.owncloud.total.store.size_in_bytes;
				$searchElasticSettings.find('.status').text(
					n('search_elastic', '{count} document uses {size} bytes', '{count} documents using {size} bytes', count, {count: count, size: size_in_bytes})
				);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not setup indexes'));
			});
		}

		var timer;

		$searchElasticSettings.on('keyup', 'input', function(e) {
			clearTimeout(timer);
			var that = this;
			//highlightInput($(this));
			timer = setTimeout(function() {
				saveServers($(that).val());
				checkStatus();
			}, 2000);
		});

		$searchElasticSettings.on('click', 'button', function(e) {
			setup();
		});

		loadServers($searchElasticSettings.find('input'));
		checkStatus();

	});

})();