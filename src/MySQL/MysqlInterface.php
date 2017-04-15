<?php

namespace Dashifen\Database\Mysql;

use Dashifen\Database\DatabaseInterface;

/**
 * Interface MysqlInterface
 * @package Dashifen\Database\Mysql
 */
interface MysqlInterface extends DatabaseInterface {
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @param array  $updates
	 * @throws MysqlException
	 * @return int
	 *
	 * inserts $values into $table, but on encountering duplicate keys, uses $updates
	 * to update instead.  can only affect a single row (i.e. $values should be an
	 * associative array representing a single set of data to insert).
	 */
	public function upsert(string $table, array $values, array $updates): int;
	
	/**
	 * @param string $table
	 * @param string $column
	 * @throws MysqlException
	 * @return array
	 *
	 * returns the list of enumerated values within a MySQL ENUM column.
	 * throws an exception if the requested column is not of the ENUM type.
	 */
	public function getEnumValues(string $table, string $column): array;
}
