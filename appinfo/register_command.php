<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2016 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

use \OCA\Search_Elastic\Command\Index;
use \OCA\Search_Elastic\Command\Reset;

/** @var Symfony\Component\Console\Application $application */
$application->add(new Index(\OC::$server->getUserManager()));
$application->add(new Reset());
