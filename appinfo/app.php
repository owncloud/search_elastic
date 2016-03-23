<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2014-2016 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

// --- always add js & css -----------------------------------------------

OCP\Util::addScript('search_elastic', 'search');
OCP\Util::addStyle('search_elastic', 'results');

// --- register settings -----------------------------------------------
\OCP\App::registerAdmin('search_elastic', 'settings/admin');

// --- add file search provider -----------------------------------------------

\OC::$server->getSearch()->removeProvider('OC\Search\Provider\File');
\OC::$server->getSearch()->registerProvider('OCA\Search_Elastic\Search\ElasticSearchProvider', array('apps' => array('files')));

// add background job for deletion
\OC::$server->getJobList()->add(new \OCA\Search_Elastic\Jobs\DeleteJob());

// --- add hooks -----------------------------------------------

//post_create is ignored, as write will be triggered afterwards anyway

//connect to the filesystem for auto updating
OCP\Util::connectHook(
		OC\Files\Filesystem::CLASSNAME,
		OC\Files\Filesystem::signal_post_write,
		'OCA\Search_Elastic\Hooks\Files',
		OCA\Search_Elastic\Hooks\Files::handle_post_write);

//connect to the filesystem for rename
OCP\Util::connectHook(
	OC\Files\Filesystem::CLASSNAME,
	OC\Files\Filesystem::signal_post_rename,
	'OCA\Search_Elastic\Hooks\Files',
	OCA\Search_Elastic\Hooks\Files::handle_post_rename);

//listen for file shares to update read permission in index
OCP\Util::connectHook(
	'OCP\Share',
	'post_shared',
	'OCA\Search_Elastic\Hooks\Files',
	OCA\Search_Elastic\Hooks\Files::handle_share);

//listen for file un shares to update read permission in index
OCP\Util::connectHook(
	'OCP\Share',
	'post_unshare',
	'OCA\Search_Elastic\Hooks\Files',
	OCA\Search_Elastic\Hooks\Files::handle_share);

//connect to the filesystem for delete
OCP\Util::connectHook(
	OC\Files\Filesystem::CLASSNAME,
	OC\Files\Filesystem::signal_delete,
	'OCA\Search_Elastic\Hooks\Files',
	OCA\Search_Elastic\Hooks\Files::handle_delete);
