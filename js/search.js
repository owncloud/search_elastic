(function() {
	OCA.Search.ElasticSearch = {
		attach: function(search) {
			search.setRenderer('search_elastic', OCA.Search.ElasticSearch.renderFileResult);
		},
		renderFileResult: function($row, result) {
			var $fileResultRow = OCA.Search.files.renderFileResult($row, result);
			if (!$fileResultRow && result.name.toLowerCase().indexOf(OC.Search.getLastQuery()) === -1) {
				/*render preview icon, show path beneath filename,
				 show size and last modified date on the right */

				$pathDiv = $('<div class="path"></div>').text(result.path);
				$row.find('td.info div.name').after($pathDiv).text(result.name);

				$row.find('td.result a').attr('href', result.link);

				if (OCA.Search.files.fileAppLoaded()) {
					OCA.Files.App.fileList.lazyLoadPreview({
						path: result.path,
						mime: result.mime_type,
						callback: function (url) {
							$row.find('td.icon').css('background-image', 'url(' + url + ')');
						}
					});
				} else {
					// FIXME how to get mime icon if not in files app
					var mimeicon = result.mime_type.replace('/', '-');
					$row.find('td.icon').css('background-image', 'url(' + OC.imagePath('core', 'filetypes/' + mimeicon) + ')');
					var dir = OC.dirname(result.path);
					if (dir === '') {
						dir = '/';
					}
					$row.find('td.info a').attr('href',
						OC.generateUrl('/apps/files/?dir={dir}&scrollto={scrollto}', {dir: dir, scrollto: result.name})
					);
				}
				$fileResultRow = $row;
			}
			if ($fileResultRow && typeof result.highlights == undefined) {
				var highlights = result.highlights.join(' … ');
				var $highlightsDiv = $('<div class="highlights"></div>').html(highlights);
				$row.find('td.info div.path').after($highlightsDiv);
			}
			return $fileResultRow;
		}
	};
})();

OC.Plugins.register('OCA.Search', OCA.Search.ElasticSearch);