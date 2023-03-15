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
	/**
	 * New. This is intended to mark the fileid as new in order to re-index.
	 * This means that the fileid has been indexed before, but it needs to
	 * be re-indexed.
	 */
	public const STATUS_NEW = 'N';
	/**
	 * Modified / Metadata changed. The metadata has changed and the fileid
	 * needs to be re-indexed
	 */
	public const STATUS_METADATA_CHANGED = 'M';
	/**
	 * Indexed. If multiple write indexes are being used, "I" should
	 * imply that the node is indexed in all the indexes.
	 * When a new write index is configured, old indexed nodes are expected
	 * to be indexed in the new index, so "I" will mean that the node is indexed
	 * only in one of the index, not in all of them.
	 * For new nodes, "I" must be setup only if the nodes has been indexed
	 * correctly in all the indexes.
	 */
	public const STATUS_INDEXED = 'I';
	/**
	 * Skipped. Some directories can be configured to be skipped. This fileid
	 * is (or should be) within a skipped directory. No action is expected
	 * on this entry.
	 */
	public const STATUS_SKIPPED = 'S';
	public const STATUS_UNINDEXED = 'U';
	/**
	 * Vanished. Fileid not found locally in the oc_filecache. The document
	 * should be removed from all the write indexes at some point.
	 * Once the document has been successfully removed from all the
	 * write indexes, the status entry should also be removed.
	 */
	public const STATUS_VANISHED = 'V';
	/**
	 * An error has happened with that entry. It's expected that we retry
	 * the indexing of this fileid at some point.
	 * Specific details about the error are stored as message
	 */
	public const STATUS_ERROR = 'E';

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
