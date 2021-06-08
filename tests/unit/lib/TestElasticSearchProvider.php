<?php

/**
 * @author Michael Barz <mbarz@owncloud.com>
 *
 * @copyright Copyright (c) 2021, ownCloud GmbH
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

namespace OCA\Search_Elastic\Tests\Unit;

use OCA\Search_Elastic\Search\ElasticSearchProvider;
use Test\TestCase;

class TestElasticSearchProvider extends TestCase {
	/**
	 * @var ElasticSearchProvider
	 */
	private $elasticSearchProvider;

	/**
	 * Set Up scenario
	 */
	public function setUp(): void {
		$this->elasticSearchProvider = new ElasticSearchProvider([]);
	}

	/**
	 * @return \string[][]
	 */
	public function provideQueries() {
		return [
			["test", "test*"],
			["*test", "*test"],
			["*test*", "*test*"],
			["this is a test", "this* is* a* test*"],
			["this is a +test", "this is a +test"],
			["this is a -test", "this is a -test"],
			["this is a-test", "this is a-test"],
			["\"this is a test\"", "\"this is a test\""],
			["this (is a test)", "this (is a test)"],
			["this is a testÑ", "this is a testÑ"],
			["this is a test?", "this is a test?"],
			["apple | red", "apple | red"],
		];
	}

	/**
	 * Test that the query format is working as expected
	 *
	 * @dataProvider provideQueries
	 * @param $query
	 * @param $result
	 */
	public function testFormatQuery($query, $result) {
		self::assertEquals($result, $this->elasticSearchProvider->formatContentQuery($query));
	}
}
