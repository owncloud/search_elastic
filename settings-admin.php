<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

\OC_Util::checkAdminUser();

\OCP\Util::addStyle('search_elastic', 'settings-admin');
\OCP\Util::addScript('search_elastic', 'settings-admin');

$tmpl = new \OCP\Template('search_elastic', 'settings-admin');

return $tmpl->fetchPage();
