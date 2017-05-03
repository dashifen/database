<?php

namespace Dashifen\Database;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\Profiler\ProfilerInterface;

abstract class AbstractDatabase implements DatabaseInterface {
	
	/**
	 * @var ExtendedPdo $dbConn
	 */
	protected $dbConn;
	
	/**
	 * @var string $database
	 */
	protected $database;
	
	/**
	 * database constructor.
	 *
	 * @param string                 $dsn
	 * @param string|null            $username
	 * @param string|null            $password
	 * @param array                  $options
	 * @param array                  $queries
	 * @param ProfilerInterface|null $profiler
	 *
	 * @throws DatabaseException
	 */
	public function __construct(
		string $dsn,
		string $username = null,
		string $password = null,
		array $options = [],
		array $queries = [],
		ProfilerInterface $profiler = null) {
		if ($this->dbConn && $this->dbConn->isConnected()) {
			$this->dbConn->disconnect();
		}
		
		$this->dbConn = new ExtendedPdo($dsn, $username, $password, $options, $queries, $profiler);
		$this->dbConn->connect();
		
		if (!$this->dbConn->isConnected()) {
			throw new DatabaseException("Unable to connect to database.");
		}
		
		$parts = explode("=", $dsn);
		$this->database = array_pop($parts);
	}
	
	/**
	 * @param \PDOException $pdoException
	 * @param string        $query
	 * @param array         $criteria
	 *
	 * @return DatabaseException
	 *
	 * uses the parameters to prepare one of our own exceptions and returns it.
	 */
	protected function prepareDatabaseException(\PDOException $pdoException, string $query, array $criteria = []): DatabaseException {
		$databaseException = new DatabaseException(
			$pdoException->getMessage(),
			(int)$pdoException->getCode(),
			$pdoException
		);
		
		$query = $this->getStatement($query, $criteria);
		$databaseException->setQuery($query);
		return $databaseException;
	}
	
	/**
	 * @return bool
	 *
	 * returns true if the object is connected to the database; false otherwise.
	 */
	public function isConnected(): bool {
		return $this->dbConn->isConnected();
	}
	
	/**
	 * @param string $table
	 *
	 * @return array
	 * @throws DatabaseException
	 */
	public function getTableColumns(string $table): array {
		$query = <<<QUERY
			SELECT COLUMN_NAME
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = :database
			AND TABLE_NAME = :table
QUERY;
		
		$params = [
			"database" => $this->database,
			"table"    => $table,
		];
		
		try {
			$results = $this->getCol($query, $params);
			return is_array($results) ? $results : [];
		} catch (\PDOException $databaseException) {
			throw $this->prepareDatabaseException($databaseException, $query, $params);
		}
	}
	
	/**
	 * @param  string $name
	 *
	 * @return int
	 *
	 * returns the ID of the most recently inserted row; name is unlikely
	 * to be necessary, according to the Aura\Sql docs, but it's important
	 * for some DB systems, e.g. PostgreSQL.
	 */
	public function getInsertedId(string $name = null): int {
		return $this->dbConn->lastInsertId($name);
	}
	
	/**
	 * @return array|null
	 *
	 * returns an array using PDO error codes as indices and error information as values
	 * or null if there were no errors to return.
	 */
	public function getError(): ?array {
		$errorCode = $this->dbConn->errorCode();
		return !is_null($errorCode) ? [$errorCode => $this->dbConn->errorInfo()] : null;
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return mixed|null
	 *
	 * given a query, returns the first column of the first row.
	 * returns null if nothing is selected or nothing could be
	 * selected.
	 */
	public function getVar(string $query, array $criteria = []) {
		try {
			return $this->dbConn->fetchValue($query, $criteria);
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns all of the returns the first column
	 * for all returned rows.  returns an empty array if nothing is
	 * selected or nothing could be selected.
	 */
	public function getCol(string $query, array $criteria = []): array {
		try {
			$results = $this->dbConn->fetchCol($query, $criteria);
			return is_array($results) ? $results : [];
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns all columns of the first row returned.
	 * returns an empty array if nothing is selected or nothing could
	 * be selected.
	 */
	public function getRow(string $query, array $criteria = []): array {
		try {
			$results = $this->dbConn->fetchOne($query, $criteria);
			return is_array($results) ? $results : [];
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns an array indexed by the first column and
	 * containing the subsequent columns as the values.  returns an
	 * empty array if nothing is selected or could be selected
	 */
	public function getMap(string $query, array $criteria = []): array {
		try {
			$results = $this->dbConn->fetchAssoc($query, $criteria);
			return is_array($results) ? $results : [];
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return array
	 *
	 * returns an array of all results selected or an empty array if nothing
	 * was selected or nothing could be selected.
	 */
	public function getResults(string $query, array $criteria = []): array {
		try {
			$results = $this->dbConn->fetchAll($query, $criteria);
			return is_array($results) ? $results : [];
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 *
	 * @throws DatabaseException
	 * @return array|null
	 *
	 * inserts $values into $table returning the created ID or IDs.
	 * returns null if nothing could be inserted.
	 */
	public function insert(string $table, array $values): ?array {
		
		// this is where we start to deviate from simply changing the names of
		// the Aura\Sql functions to match existing function calls within my other
		// applications.  we have two options here:  inserting a single row or
		// multiple ones.  either way the $values array tells us what to do.  if
		// it's an array of arrays, then we do multiples.
		
		try {
			return isset($values[0]) && is_array($values[0])
				? $this->insertMultiple($table, $values)
				: $this->insertSingle($table, $values);
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $table, $values);
		}
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 *
	 * @return mixed|null
	 *
	 * used when inserting multiple rows into the database in a single query.
	 * more complex than inserting a single row multiple times, but some tests
	 * online indicate this is faster.
	 */
	protected function insertMultiple(string $table, array $values): ?array {
		
		// first, we need to verify that our columns will provide us the ability to
		// actually insert.  the verifyColumns() method will assist with that below.
		// that method will throw Exceptions if they fail to verify which we'll let
		// bubble up so that the programmer using this library can handle them.
		
		$columns = array_keys($values[0]);
		if ($this->verifyColumns($columns, $values)) {
			
			// now, we need a separate parenthetical $values statement for each of our
			// $values.  then, we need to merge those parenthetical statements into a
			// series of them.  e.g., if we're inserting sets of two columns, this'll
			// make (?,?), (?,?), (?,?)... for us.
			
			$parenthetical = $this->placeholders(sizeof($columns));
			$parentheticals = $this->placeholders(sizeof($values), $parenthetical, false);
			$columns = join(", ", $columns);
			
			$statement = "INSERT INTO $table ($columns) VALUES $parentheticals";
			
			return $this->insertExecute($statement, $this->mergeBindings($values));
		}
		
		// currently, the verifyColumns() method either returns true or throws an exception.
		// but in case that changes or is altered in by a child of this object, we'll want to
		// return null here to match the expected interface of the insert method above.
		
		return null;
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 *
	 * @return mixed|null
	 *
	 * produces a SQL query using the parameters
	 */
	protected function insertSingle(string $table, array $values): ?array {
		
		// this one is far less complex that our insertMultiple above.  we'll build our
		// statement and then pass control over to insertExecute which'll actually perform
		// our statement and return the appropriate results up the call stack.
		
		$statement = $this->insertBuild($table, $values);
		return $this->insertExecute($statement, $values);
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 *
	 * @return string
	 *
	 * as the mysql implementation of this object includes an upsert query which
	 * begins with teh same syntax as our insert here, this method builds the insert
	 * and returns it.  this allows us to use the same logic for both the general
	 * insert query as well as the mysql specific upsert one.
	 */
	protected function insertBuild(string $table, array $values): string {
		$columns = join(", ", array_keys($values));
		$bindings = $this->placeholders(sizeof($values));
		return "INSERT INTO $table ($columns) VALUES $bindings";
	}
	
	/**
	 * @param array $columns
	 * @param array $values
	 *
	 * @throws DatabaseException
	 * @return bool
	 */
	protected function verifyColumns(array $columns, array $values): bool {
		
		// to verify our columns, we need to be sure that our array of $values to
		// insert both (a) contains the same number of columns, (b) is always
		// the same list of columns.  for (b) this means that one row can't insert
		// and ID and email address while another row tries to insert an ID and
		// phone number.
		
		// TODO: test to see if skipping $values[0] would save time.
		
		$columnCount = sizeof($columns);
		
		foreach ($values as $value) {
			
			// our first test is to see if the count of columns in our $value
			// matches the count we expect to find.  if not, we'll throw an
			// Exception to help the calling scope know what went wrong here.
			
			if (sizeof($value) !== $columnCount) {
				throw new DatabaseException("insertMultiple failed: mismatched column counts");
			}
			
			// if we're still testing columns, then we'll want to see that there's
			// no difference between the names of our $columns and the keys of this
			// specific $value.
			
			$theseColumns = array_keys($value);
			$difference = array_diff($columns, $theseColumns);
			if (sizeof($difference) !== 0) {
				throw new DatabaseException("insertMultiple failed: mismatched columns");
			}
		}
		
		// if we looped through the entire list of $values and didn't throw an Exception,
		// then we've verified that our columns will allow us to insert these multiple
		// rows.  so, we return true.
		
		return true;
	}
	
	/**
	 * @param string $statement
	 * @param array  $values
	 *
	 * @throws DatabaseException
	 * @return array|null
	 */
	
	protected function insertExecute(string $statement, array $values): ?array {
		
		// our insert method should return either a single ID or a list of created IDs.
		// we can use the ExtendedPdo's fetchAffected method to determine how many were
		// inserted and then use that to determine our return value.  if nothing at all
		// was inserted, we return null.
		
		$affected = $this->dbConn->fetchAffected($statement, array_values($values));
		
		if ($affected === 0) {
			return null;
		}
		
		if ($affected === 1) {
			return [$this->getInsertedId()];
		}
		
		// if we haven't returned, then we inserted more than 1 row.  we can construct a
		// list of the IDs that were created by using the last ID and range as follows.
		// to identify our first ID, it would seem like $lastId - $affected would be enough,
		// but if we inserted 5 rows ending at 10, 10 - 5 = 5 and we don't want a range to
		// be [10,9,8,7,6,5] because that's six IDs.  so, we want to do 10 - (5 - 1) which
		// would result in [10,9,8,7,6] which is the correct response.
		
		$lastId = $this->getInsertedId();
		$firstId = $lastId - ($affected - 1);
		return range($lastId, $firstId);
	}
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return int
	 *
	 * updates $values within $table based on $criteria.  returns the
	 * number of rows changed by the update (including zero).
	 */
	public function update(string $table, array $values, array $criteria = []): int {
		
		// like our insertion methods above, this one can impact one or more rows
		// and we want to return the number of rows we alter.  unlike the insertion
		// methods, all we need to do is build our statement which isn't too hard for
		// us to do.
		
		$updates = $where = "";
		$valueColumns = array_keys($values);
		foreach ($valueColumns as $column) {
			$updates .= $column . " = ? ";
		}
		
		$statement = "UPDATE $table SET $updates";
		
		if (sizeof($criteria) > 0) {
			$whereColumns = array_keys($criteria);
			foreach ($whereColumns as $column) {
				$where .= $column . " = ? ";
			}
			
			$statement .= " WHERE $where";
		}
		
		// now that we've built our statement, we need to combine the values and criteria values
		// which are then bound to the statement by the ExtendedPdo object.  then, we can perform
		// the statement and return the number of affected rows.
		
		$bindings = $this->mergeBindings($values, $criteria);
		
		try {
			return $this->dbConn->fetchAffected($statement, $bindings);
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $statement, $bindings);
		}
	}
	
	/**
	 * @param string $table
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return int
	 *
	 * deletes from $table based on $criteria.  returns the number of
	 * rows deleted (including zero).
	 */
	public function delete(string $table, array $criteria = []): int {
		
		// this one is like the insertSingle above in that it uses its parameters
		// to build a query.  unlike that one, we execute it here and return the
		// number of deleted rows.
		
		$statement = "DELETE FROM $table";
		
		if (sizeof($criteria) > 0) {
			
			// we want to take our column names and turn them into a string
			// like x=? AND y=? AND z=? so that we can tack it onto our
			// $statement
			
			$columns = array_keys($criteria);
			array_walk($columns, function(&$x) {
				$x = $x . "=?";
			});
			$statement .= " WHERE " . join(" AND ", $columns);
		}
		
		$bindings = array_values($criteria);
		
		try {
			return $this->dbConn->fetchAffected($statement, $bindings);
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $statement, $bindings);
		}
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @throws DatabaseException
	 * @return bool
	 *
	 * sometimes, the above helper functions aren't enough.  this method
	 * runs the given query using the criteria and returns true if it worked
	 * and false otherwise.  note:  this will not return the results of a
	 * SELECT query; it's meant more for things like an INSERT INTO SELECT FROM
	 * style query that won't quite work using the insert method above.
	 */
	public function runQuery(string $query, array $criteria = []): bool {
		
		// the ExtendedPdo::perform method executes our query using the specified
		// criteria and returns a PDOStatement object.  the errorCode() method of
		// that object returns a SQLSTATE code identifying any problems that cropped
		// up during that execution.  the code 00000 indicates that there were no
		// such problems, so we can look for that in returning our boolean value
		// here.
		
		try {
			$pdoStatement = $this->dbConn->perform($query, $criteria);
		} catch (\PDOException $pdoException) {
			throw $this->prepareDatabaseException($pdoException, $query, $criteria);
		}
		
		$errorCode = $pdoStatement->errorCode();
		return $errorCode == "00000";
	}
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 *
	 * @return string
	 *
	 * source: http://stackoverflow.com/a/12015992/360838 (accessed 2017-04-13)
	 */
	public function getStatement(string $query, array $criteria = []): string {
		
		// the ExtendedPdo object uses its parser to manipulate the criteria it
		// receives to do additional tasks like handling arrays for IN () clauses.
		// to try and get as close to the statement that is run against the
		// database as possible, we'll do that here, too.
		
		$parser = $this->dbConn->getParser();
		list($query, $criteria) = $parser->rebuild($query, $criteria);
		
		// now, we'll use the (slightly modified) code from stack overflow
		// (referenced above) to builds a string version of the statement.
		
		$keys = array();
		foreach ($criteria as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:' . $key . '/';
			} else {
				$keys[] = '/[?]/';
			}
			
			if (is_array($value)) {
				$criteria[$key] = implode(',', $value);
			}
			
			if (is_null($value)) {
				$criteria[$key] = 'NULL';
			}
		}
		
		return preg_replace($keys, $criteria, $query);
	}
	
	/**
	 * @param int    $count
	 * @param string $placeholder
	 * @param bool   $surround
	 *
	 * @return string
	 *
	 * returns a string appropriate for use within a statement as the placeholders
	 * for a series of bound values.  e.g., for a count of 3, returns (?, ?, ?).
	 */
	protected function placeholders(int $count, string $placeholder = '?', bool $surround = true): string {
		$temp = join(", ", array_pad([], $count, $placeholder));
		
		// the above line simply gives us a comma separated string of $count placeholders.
		// by default we want to surround them with parentheses, but sometimes maybe we
		// don't.  the $surround bool will tells us how to proceed.
		
		return $surround ? "($temp)" : $temp;
	}
	
	/**
	 * @param array[] ...$arrays
	 *
	 * @return array
	 *
	 * merges the list of arrays it receives from the calling scope into a single
	 * array adding each value sequentially without overriding keys or other such
	 * problems.
	 */
	protected function mergeBindings(array ...$arrays): array {
		
		// at first blush, it seems like one could use something like array_merge()
		// to build our return value, but because that method would overwrite matching
		// keys in early arrays with values in the latter ones, we can't rely on it.
		// instead, what we actually want to do is flatten our $arrays which we've
		// learned to do here: http://stackoverflow.com/a/1320156/360838.
		
		$bindings = [];
		
		array_walk_recursive($arrays, function($x) use (&$bindings) {
			$bindings[] = $x;
		});
		
		return $bindings;
	}
}
