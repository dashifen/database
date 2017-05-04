<?php

namespace Dashifen\Database\MySQL;

use Dashifen\Database\AbstractDatabase;
use Dashifen\Database\DatabaseException;

/**
 * Class MysqlDatabase
 *
 * @package Dashifen\Database\MySQL\
 */
class MysqlDatabase extends AbstractDatabase implements MysqlInterface {
	public function __construct($dsn, $username = null, $password = null, array $options = [], array $queries = [], $profiler = null) {
		parent::__construct($dsn, $username, $password, $options, $queries, $profiler);
		$this->columnPrefix = "`";
		$this->columnSuffix = "`";
	}
	
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @param array  $updates
	 *
	 * @throws MysqlException
	 * @return int
	 *
	 * inserts $values into $table, but on encountering duplicate keys, uses $updates
	 * to update instead.  can only affect a single row (i.e. $values should be an
	 * associative array representing a single set of data to insert).
	 */
	public function upsert(string $table, array $values, array $updates): int {
		$insert = $this->insertBuild($table, $values);
		
		// our illustrious (and attractive) programmer provided the means to grab
		// the built INSERT query from our parent as we can see above.  now, we just
		// have to add the ON DUPLICATE KEY UPDATE ssyntax to that foundation.
		
		$temp = [];
		$columns = array_keys($updates);
		foreach ($columns as $column) {
			$temp[] = sprintf("%s%s%s = ?", $this->columnPrefix, $column, $this->columnSuffix);
		}
		
		$insert .= " ON DUPLICATE KEY UPDATE " . join(", ", $temp);
		
		// that builds our query, now we just have to merge the $values and $updates
		// arrays to construct the array constituting our bindings and execute our
		// query.
		
		$bindings = $this->mergeBindings($values, $updates);
		
		try {
			return $this->dbConn->fetchAffected($insert, $bindings);
		} catch (DatabaseException $databaseException) {
			throw $this->prepareMysqlException($databaseException, $insert, $bindings);
		}
	}
	
	/**
	 * @param \Exception $databaseException
	 * @param string     $query
	 * @param array      $criteria
	 *
	 * @return MysqlException
	 */
	protected function prepareMysqlException(\Exception $databaseException, string $query, array $criteria = []): MysqlException {
		$mysqlException = new MysqlException(
			$databaseException->getMessage(),
			$databaseException->getCode(),
			$databaseException
		);
		
		$mysqlException->setQuery($this->getStatement($query, $criteria));
		return $mysqlException;
	}
	
	/**
	 * @param string $table
	 * @param string $column
	 *
	 * @throws MysqlException
	 * @return array
	 *
	 * returns the list of enumerated values within a MySQL ENUM column.
	 * throws an exception if the requested column is not of the ENUM (or
	 * SET) type.
	 */
	public function getEnumValues(string $table, string $column): array {
		$query = <<<QUERY
			SELECT COLUMN_TYPE
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = :database
			AND TABLE_NAME = :table
			AND COLUMN_NAME = :column
			AND DATA_TYPE IN ('enum','set')
QUERY;
		
		$params = [
			"database" => $this->database,
			"table"    => $table,
			"column"   => $column,
		];
		
		try {
			$type = $this->getVar($query, $params);
		} catch (DatabaseException $databaseException) {
			throw $this->prepareMysqlException($databaseException, $query, $params);
		}
		
		// now we want to (a) confirm that this is an enum (or set) field, and
		// if so (b) extract the set of possible values that it represents.
		// string manipulation to the rescue!
		
		if (strpos($type, "enum(") === 0 || strpos($type, "set(") === 0) {
			
			// the type information is in the form of enum('a','b','c',...,'z').
			// we can match that information with a regular expression as follows.
			
			preg_match_all("/'([^']+)'/", $type, $matches);
			$values = $matches[1];
		} else {
			throw new MysqlException("getEnumValues failed: type mismatch");
		}
		
		return $values;
	}
}
