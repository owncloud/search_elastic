<?php
/**
 * ownCloud
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 * @copyright (C) 2019 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Search_Elastic\Tests\Unit\AppInfo;

use OCA\Search_Elastic\Application;
use Test\TestCase;

class ApplicationTest extends TestCase {
	public function testInit() {
		$app = new Application();
		$result = $app->init();
		$this->assertNull($result);
	}
}
