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
			$.get(
				OC.generateUrl('apps/search_elastic/settings/status')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error');
				$searchElasticSettings.find('.status').text('');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));
				console.debug(result.responseJSON.status);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success');
				$searchElasticSettings.find('.status').text(result.responseJSON.message);
				$searchElasticSettings.find('button').text(t('search_elastic', 'Setup index'));
			});
		}
		function setup() {
			return $.post(
				OC.generateUrl('apps/search_elastic/setup')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error');
				$searchElasticSettings.find('.status').text('');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success');
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