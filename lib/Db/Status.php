<?php
/**
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Phil Davis <phil@jankaritech.com>
 * @author Saugat Pachhai <suagatchhetri@outlook.com>
 * @author Sujith H <sharidasan@owncloud.com>
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

namespace OCA\Search_Elastic\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method integer getFileId()
 * @method void setFileId(integer $fileId)
 * @method string getStatus()
 * @method setStatus(string $status)
 * @method setMessage(string $status)
 */
class Status extends Entity {
	const STATUS_NEW = 'N';
	const STATUS_METADATA_CHANGED = 'M';
	const STATUS_INDEXED = 'I';
	const STATUS_SKIPPED = 'S';
	const STATUS_UNINDEXED = 'U';
	const STATUS_VANISHED = 'V';
	const STATUS_ERROR = 'E';

	public $fileId;
	public $status;
	public $message;

	// we use fileId as the primary key
	private $_fieldTypes = ['fileId' => 'integer'];

	/**
	 * @param int $fileId
	 * @param string $status
	 * @param string $message
	 */
	public function __construct($fileId = null, $status = null, $message = null) {
		// use setters to mark properties as updated
		$this->setFileId($fileId);
		$this->setStatus($status);
		$this->setMessage($message);
	}
	/**
	 * @return array with attribute and type
	 */
	public function getFieldTypes() {
		return $this->_fieldTypes;
	}

	/**
	 * Adds type information for a field so that its automatically casted to
	 * that value once its being returned from the database
	 * @param string $fieldName the name of the attribute
	 * @param string $type the type which will be used to call setType()
	 */
	protected function addType($fieldName, $type) {
		$this->_fieldTypes[$fieldName] = $type;
	}

	/**
	 * we need to overwrite the setter because it would otherwise use _fieldTypes of the Entity class
	 *
	 * @param string $name
	 * @param array $args
	 * @throws \BadFunctionCallException
	 */
	protected function setter($name, $args) {
		// setters should only work for existing attributes
		if (\property_exists($this, $name)) {
			if ($args[0] === $this->$name) {
				return;
			}
			$this->markFieldUpdated($name);

			// if type definition exists, cast to correct type
			if ($args[0] !== null && \array_key_exists($name, $this->_fieldTypes)) {
				\settype($args[0], $this->_fieldTypes[$name]);
			}
			$this->$name = $args[0];
		} else {
			throw new \BadFunctionCallException($name .
				' is not a valid attribute');
		}
	}

	/**
	 * Transform a database column name to a property
	 * @param string $columnName the name of the column
	 * @return string the property name
	 */
	public function columnToProperty($columnName) {
		if ($columnName === 'fileid') {
			$property = 'fileId';
		} else {
			$property = parent::columnToProperty($columnName);
		}
		return $property;
	}

	/**
	 * Transform a property to a database column name
	 * for search_lucene we don't magically insert a _ for CamelCase
	 * @param string $property the name of the property
	 * @return string the column name
	 */
	public function propertyToColumn($property) {
		$column = \strtolower($property);
		return $column;
	}
}
