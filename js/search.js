(function() {
	/**
	 * Highlight fragments are built from attacker controlled file content, so they
	 * must never be handed to .html(). Rebuild them from an inert document, keeping
	 * text and the <em> markers elasticsearch wraps the matched terms in, and
	 * dropping every other element together with its subtree.
	 *
	 * Stripping - not escaping - is deliberate: elasticsearch already encodes the
	 * fragment ('encoder' => 'html'), escaping again here would show the encoded
	 * entities to the user.
	 *
	 * @param {string} html highlight fragment
	 * @param {Document} target document the returned nodes belong to
	 * @returns {Array} sanitized nodes, ready to be appended
	 */
	function sanitizeHighlights(html, target) {
		// parsing into a 'text/html' document is inert: no script is executed and
		// no resource is loaded, unlike jQuery.parseHTML which builds the fragment
		// against the live document
		var parsed = new DOMParser().parseFromString(html, 'text/html');

		function convert(node) {
			var nodes = [];
			for (var i = 0; i < node.childNodes.length; i++) {
				var child = node.childNodes[i];
				if (child.nodeType === Node.TEXT_NODE) {
					nodes.push(target.createTextNode(child.nodeValue));
				} else if (child.nodeType === Node.ELEMENT_NODE && child.tagName === 'EM') {
					var em = target.createElement('em');
					var grandChildren = convert(child);
					for (var j = 0; j < grandChildren.length; j++) {
						em.appendChild(grandChildren[j]);
					}
					nodes.push(em);
				}
				// anything else is dropped, subtree included
			}
			return nodes;
		}

		return convert(parsed.body);
	}

	OCA.Search.ElasticSearch = {
		attach: function(search) {
			search.setRenderer('search_elastic', OCA.Search.ElasticSearch.renderFileResult);
		},
		renderFileResult: function($row, result) {
			var $fileResultRow;
			if (result.mime_type && result.mime_type === 'httpd/unix-directory') {
				$fileResultRow = OCA.Search.files.renderFolderResult($row, result);
			} else {
				$fileResultRow = OCA.Search.files.renderFileResult($row, result);
			}
			if (!$fileResultRow && result.name.toLowerCase().indexOf(OC.Search.getLastQuery()) === -1) {
				/*render preview icon, show path beneath filename,
				 show size and last modified date on the right */

				var $pathDiv = $('<div class="path"></div>').text(result.path);
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
			if ($fileResultRow && typeof result.highlights === 'object' && result.highlights !== null) {
				var highlights = result.highlights.join(' … ');
				var $highlightsDiv = $('<div class="highlights"></div>')
					.append(sanitizeHighlights(highlights, document));
				$row.find('td.info div.path').after($highlightsDiv);
			}
			return $fileResultRow;
		}
	};
})();

OC.Plugins.register('OCA.Search', OCA.Search.ElasticSearch);