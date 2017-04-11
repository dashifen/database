<?php

namespace Dashifen\Database\MySQL;

use Dashifen\Database\AbstractDatabase;

/**
 * Class MysqlDatabase
 * @package Dashifen\Database\MySQL\
 */
class MysqlDatabase extends AbstractDatabase implements MysqlInterface {
	
	/**
	 * @return null|string
	 *
	 * executes a SELECT DATABASE() function and returns its results to the
	 * calling scope.
	 */
	public function getDatabase(): ?string {
		return $this->getVar("SELECT DATABASE()", []);
	}
	
	/**
	 * @param string $database
	 * @returns bool
	 *
	 * executes a USE statement against the database to select the given database.
	 */
	public function setDatabase(string $database): bool {
		return $this->runQuery("USE :database", ["database" => $this->escapeName($database)]);
	}
	
	/**
	 * @param string $table
	 * @return array
	 *
	 * MySQL has a SHOW COLUMNS syntax that allows us to get information about what
	 * the given table stores.  the first column of its results is the column names.
	 * we'll return that information here.
	 */
	public function getTableColumns(string $table): array {
		return $this->getCol("SHOW COLUMNS FROM :table", ["table" => $this->escapeName($table)]);
	}
	
	
	/**
	 * @param string $name
	 * @return string
	 *
	 * MySQL uses back ticks to escape proper names so we'll add those here, but if
	 * this is a fully qualified name (e.g. database.table.column) then we need to
	 * escape each part of it; `database.table.column` is seen as a single name, not
	 * three.  this does mean that you can't use dots in proper names with this
	 * library, but you probably shouldn't do that anyway!
	 */
	protected function escapeName(string $name): string {
		$parts = strpos(".", $name) !== false ? explode(".", $name) : [$name];
		
		// array reduce can be used to take our $parts and put them back together as
		// a string while adding back ticks to each of them.  but, this would produce
		// a string like this:  `database``table``column` which isn't quite right.
		// so, we use str_replace to correct it.
		
		$escaped = array_reduce($parts, function($r, $p) { return $r . "`$p`"; }, "");
		return str_replace("``", "`.`", $escaped);
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @param array  $updates
	 *
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
		// have to add the ON DUPLICATE KEY UPDATE syntax to that foundation.
		
		$temp = [];
		$columns = $this->escapeNames(array_keys($updates));
		foreach ($columns as $column) {
			$temp[] = "$column = ?";
		}
		
		$insert .= " ON DUPLICATE KEY UPDATE " . join(", ", $temp);
		
		// that builds our query, now we just have to merge the $values and $updates
		// arrays to construct the array constituting our bindings and execute our
		// query.
		
		return $this->dbConn->fetchAffected($insert, array_merge($values, $updates));
	}
	
	/**
	 * @param string $table
	 * @param string $column
	 * @throws MysqlException
	 * @return array
	 *
	 * returns the list of enumerated values within a MySQL ENUM column.
	 * throws an exception if the requested column is not of the ENUM type.
	 */
	public function getEnumValues(string $table, string $column): array {
		$table   = $this->escapeName($table);
		$results = $this->getRow("SHOW COLUMNS FROM $table LIKE :column", ["column"=>$column]);
		$values  = [];
		
		if (isset($results["Type"])) {
			
			// now we want to (a) confirm that this is an enum (or set) field, and
			// if so (b) extract the set of possible values that it represents.
			// string manipulation to the rescue!
			
		 	$type = $results["Type"];
		 	if (strpos($type, "enum(") !== false || strpos($type, "set(") !== false) {
			
		 		// the type information is in the form of enum('a','b','c',...,'z').
				// we can match that information with a regular expression as follows.
				
				preg_match_all("/'([^']+)'/", $type, $matches);
				$values = $matches[1];
			} else {
		 		throw new MysqlException("getEnumValues failed: type mismatch");
			}
		}
		
		return $values;
	}
}
