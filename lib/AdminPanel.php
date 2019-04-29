<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2017 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic;

use OCP\Settings\ISettings;

class AdminPanel implements ISettings {
	public function getPanel() {
		return new \OCP\Template('search_elastic', 'settings/admin');
	}

	public function getSectionID() {
		return 'search';
	}

	public function getPriority() {
		return 50;
	}
}
