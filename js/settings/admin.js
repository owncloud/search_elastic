(function(){

	$(document).ready(function() {
        hideUserPassSettings();
		var $searchElasticSettings = $('#searchElasticSettings');

		function loadServers($element) {
			return $.get(
				OC.generateUrl('apps/search_elastic/settings/servers')
			).done(function( result ) {
				var host = result.servers;
				var user = result.server_user;
				var pass = result.server_password;
				$element.val(host);
				if(user) {
					showSelectedAutenticationSettings('userPassOption');
					$('#user').val(user);
					$('#password').val(pass);
					$('select').val('userPassOption');
				}

			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not load servers'));
			});
		}
		function saveServers(servers, user, pass) {
			return $.post(
				OC.generateUrl('apps/search_elastic/settings/servers'),
				{ servers: servers, user: user, pass: pass }
			).fail(function( result ) {
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

		$('#saveConfiguration').on('click', function(e) {
			var user = "";
			var pass = "";
			var host = $('#host').val();
			var option = $('select').children("option:selected").val();
			if (option == 'userPassOption') {
				if(!userOrPasswordEmpty()) {
					user = $('#user').val();
					pass = $('#password').val();
				} else {
					return;
				}
			}
			saveServers(host, user, pass)
			.done(function() {
				checkStatus();
			});
		});

		function userOrPasswordEmpty() {
			var user = $('#user').val();
			var password = $('#password').val();
			if (user == '' || password == '') {
				OC.dialogs.alert('User or Password empty.', t('search_elastic', 'Invalid parameters'));
				return true;				
			}
			return false;
		}

		$('#rescan').on('click', function(e) {
			setup();
		});

		$searchElasticSettings.on('click', 'input[type="checkbox"]', function(e) {
			toggleScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		});

		$('#authenticationSettings').change(function(){
			var option = $(this).children("option:selected").val();
			showSelectedAutenticationSettings(option);
		  });
		
		function showUserPassSettings() {
			var $userPassSettings = $('#userPassSettings');
			$userPassSettings.removeClass('hide');
			$userPassSettings.addClass('show');
		}

		function hideUserPassSettings() {
			var $userPassSettings = $('#userPassSettings');
			$userPassSettings.removeClass('show');
			$userPassSettings.addClass('hide');
		}

		function showSelectedAutenticationSettings(option) {
			if (option == 'userPassOption') {
				showUserPassSettings();
			}
			else {
				hideUserPassSettings();
			}
		}
		
		loadServers($searchElasticSettings.find('#host'));
		getScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		checkStatus();

	});

})();
