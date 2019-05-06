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

	// we need to overwrite the setter because it would otherwise use _fieldTypes of the Entity class
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
