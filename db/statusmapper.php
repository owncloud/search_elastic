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

namespace OCA\Search_Elastic\Db;

use OC\Files\Filesystem;
use OC\Files\Mount\Mount;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\Mapper;
use OCP\IDb;
use OCP\ILogger;

class StatusMapper extends Mapper {

	private $logger;

	private $scanExternalStorages;

	public function __construct(IDb $db, ILogger $logger, $scanExternalStorages = true){
		parent::__construct($db, 'search_elastic_status', '\OCA\Search_Elastic\Db\Status');
		$this->logger = $logger;
		$this->scanExternalStorages = $scanExternalStorages;
	}

	/**
	 * Deletes a status from the table
	 * @param Entity $status the status that should be deleted
	 */
	public function delete(Entity $status){
		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `fileid` = ?';
		$this->execute($sql, array($status->getFileId()));
	}

	/**
	 * Clears all status entries from the table
	 */
	public function clear() {
		$this->execute('DELETE FROM `' . $this->tableName . '`');
	}

	/**
	 * Creates a new entry in the db from an entity
	 * @param Status $entity the entity that should be created
	 * @return Status the saved entity with the set id
	 */
	public function insert(Entity $entity){
		// get updated fields to save, fields have to be set using a setter to
		// be saved
		$properties = $entity->getUpdatedFields();
		$values = '';
		$columns = '';
		$params = array();

		// build the fields
		$i = 0;
		foreach($properties as $property => $updated) {
			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);

			$columns .= '`' . $column . '`';
			$values .= '?';

			// only append colon if there are more entries
			if($i < count($properties)-1){
				$columns .= ',';
				$values .= ',';
			}

			array_push($params, $entity->$getter());
			$i++;

		}

		$sql = 'INSERT INTO `' . $this->tableName . '`(' .
			$columns . ') VALUES(' . $values . ')';

		$this->execute($sql, $params);

		$entity->setFileId((int) $this->db->getInsertId($this->tableName));
		return $entity;
	}

	/**
	 * Updates an entry in the db from a status
	 * @param Entity $entity the status that should be created
	 * @return Entity|null
	 * @throws \InvalidArgumentException if entity has no id
	 */
	public function update(Entity $entity){
		// if entity wasn't changed it makes no sense to run a db query
		$properties = $entity->getUpdatedFields();
		if(count($properties) === 0) {
			return $entity;
		}

		// entity needs an id
		$fileId = $entity->getFileId();
		if($fileId === null){
			throw new \InvalidArgumentException(
				'Entity which should be updated has no fileId');
		}

		// get updated fields to save, fields have to be set using a setter to
		// be saved
		// don't update the fileId field
		unset($properties['fileId']);

		$columns = '';
		$params = array();

		// build the fields
		$i = 0;
		foreach($properties as $property => $updated) {

			$column = $entity->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);

			$columns .= '`' . $column . '` = ?';

			// only append colon if there are more entries
			if($i < count($properties)-1){
				$columns .= ',';
			}

			array_push($params, $entity->$getter());
			$i++;
		}

		$sql = 'UPDATE `' . $this->tableName . '` SET ' .
			$columns . ' WHERE `fileid` = ?';
		array_push($params, $fileId);

		$this->execute($sql, $params);
	}


	/**
	 * get the list of all unindexed files for the user
	 *
	 * @return array
	 */
	public function getUnindexed() {
		$files = array();
		//TODO use server api for mounts & root
		$absoluteRoot = Filesystem::getView()->getAbsolutePath('/');
		$mounts = Filesystem::getMountPoints($absoluteRoot);
		$mount = Filesystem::getMountPoint($absoluteRoot);
		if (!in_array($mount, $mounts)) {
			$mounts[] = $mount;
		}

		$query = $this->db->prepareQuery('
			SELECT `*PREFIX*filecache`.`fileid`
			FROM `*PREFIX*filecache`
			LEFT JOIN `' . $this->tableName . '`
			ON `*PREFIX*filecache`.`fileid` = `' . $this->tableName . '`.`fileid`
			WHERE `storage` = ?
			AND ( `status` IS NULL OR `status` = ? )
		');

		foreach ($mounts as $mount) {
			if (is_string($mount)) {
				$storage = Filesystem::getStorage($mount);
			} else if ($mount instanceof Mount) {
				$storage = $mount->getStorage();
			} else {
				$storage = null;
				$this->logger->
					debug( 'expected string or instance of \OC\Files\Mount\Mount got ' . json_encode($mount) );
			}
			//only index external files if the admin enabled it
			if ($this->scanExternalStorages || $storage->isLocal()) {
				$cache = $storage->getCache();
				$numericId = $cache->getNumericStorageId();

				$result = $query->execute(array($numericId, Status::STATUS_NEW));

				while ($row = $result->fetchRow()) {
					$files[] = $row['fileid'];
				}
			}
		}
		return $files;
	}


	/**
	 * @param $fileId
	 * @return Status
	 */
	public function getOrCreateFromFileId($fileId) {
		$sql = '
			SELECT `fileid`, `status`, `message`
			FROM ' . $this->tableName . '
			WHERE `fileid` = ?
		';
		try {
			return $this->findEntity($sql, array($fileId));
		} catch (DoesNotExistException $e) {
			$status = new Status($fileId, Status::STATUS_NEW);
			return $this->insert($status);
		}
	}

	// always write status to db immediately
	public function markNew(Status $status) {
		$status->setStatus(Status::STATUS_NEW);
		return $this->update($status);
	}

	public function markIndexed(Status $status) {
		$status->setStatus(Status::STATUS_INDEXED);
		return $this->update($status);
	}

	public function markSkipped(Status $status, $message = null) {
		$status->setStatus(Status::STATUS_SKIPPED);
		$status->setMessage($message);
		return $this->update($status);
	}

	public function markUnIndexed(Status $status) {
		$status->setStatus(Status::STATUS_UNINDEXED);
		return $this->update($status);
	}

	public function markVanished(Status $status) {
		$status->setStatus(Status::STATUS_VANISHED);
		return $this->update($status);
	}

	public function markError(Status $status, $message = null) {
		$status->setStatus(Status::STATUS_ERROR);
		$status->setMessage($message);
		return $this->update($status);
	}

	/**
	 * @return int[]
	 */
	public function getDeleted() {
		$files = array();

		$query = $this->db->prepareQuery('
			SELECT `' . $this->tableName . '`.`fileid`
			FROM `' . $this->tableName . '`
			LEFT JOIN `*PREFIX*filecache`
				ON `*PREFIX*filecache`.`fileid` = `' . $this->tableName . '`.`fileid`
			WHERE `*PREFIX*filecache`.`fileid` IS NULL
		');

		$result = $query->execute();

		while ($row = $result->fetchRow()) {
			$files[] = $row['fileid'];
		}

		return $files;

	}

}
