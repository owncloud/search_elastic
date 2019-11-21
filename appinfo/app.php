<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Barz <mbarz@owncloud.com>
 * @author Patrick Jahns <github@patrickjahns.de>
 * @author VicDeo <victor.dubiniuk@gmail.com>
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

use OCA\Search_Elastic\Application;

if ((@include_once __DIR__ . '/../vendor/autoload.php') === false) {
	throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

$app = new Application();
$app->init();
