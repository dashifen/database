<?php

namespace Dashifen\Database;

/**
 * Interface DatabaseInterface
 * @package Dashifen\Database
 */
interface DatabaseInterface	{
	
	/**
	 * @return bool
	 *
	 * returns true if the object is connected to the database; false otherwise.
	 */
	public function isConnected(): bool;
	
	/**
	 * @param string $table
	 * @throws DatabaseException
	 * @return string[]
	 *
	 * returns an array of the column names within $table
	 * throws exception if table doesn't exist
	 */
	public function getTableColumns(string $table): array;
	
	/**
	 * @return null|string
	 *
	 * returns the name of the database to which we're connected or
	 * null if no connection has been made.
	 */
	public function getDatabase(): ?string;

	/**
	 * @param  string $name
	 * @return int
	 *
	 * returns the ID of the most recently inserted row; name is unlikely
	 * to be necessary, according to the Aura\Sql docs, but it's important
	 * for some DB systems, e.g. PostgreSQL.
	 */
	public function getInsertedId(string $name = null): int;
	
	/**
	 * @return array|null
	 *
	 * returns an array using PDO error codes as indices and error information as values
	 * or null if there were no errors to return.
	 */
	public function getError(): ?array;
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return mixed
	 *
	 * given a query, returns the first column of the first row.
	 * returns null if nothing is selected or nothing could be
	 * selected.
	 */
	public function getVar(string $query, array $criteria = []);
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns all of the returns the first column
	 * for all returned rows.  returns an empty array if nothing is
	 * selected or nothing could be selected.
	 */
	public function getCol(string $query, array $criteria = []): array;
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns all columns of the first row returned.
	 * returns an empty array if nothing is selected or nothing could
	 * be selected.
	 */
	public function getRow(string $query, array $criteria = []): array;
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return array
	 *
	 * given a query, returns an array indexed by the first column and
	 * containing the subsequent columns as the values.  returns an
	 * empty array if nothing is selected or could be selected
	 */
	public function getMap(string $query, array $criteria = []): array;
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return array
	 *
	 * returns an array of all results selected or an empty array if nothing
	 * was selected or nothing could be selected.
	 */
	public function getResults(string $query, array $criteria = []): array;
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @throws DatabaseException
	 * @return array|null
	 *
	 * inserts $values into $table returning the created ID or IDs.
	 * returns null if nothing could be inserted.
	 */
	public function insert(string $table, array $values): ?array;
	
	/**
	 * @param string $table
	 * @param array  $values
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return int
	 *
	 * updates $values within $table based on $criteria.  returns the
	 * number of rows changed by the update (including zero).
	 */
	public function update(string $table, array $values, array $criteria = []): int;
	
	/**
	 * @param string $table
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return int
	 *
	 * deletes from $table based on $criteria.  returns the number of
	 * rows deleted (including zero).
	 */
	public function delete(string $table, array $criteria = []): int;
	
	/**
	 * @param string $query
	 * @param array  $criteria
	 * @throws DatabaseException
	 * @return bool
	 *
	 * sometimes, the above helper functions aren't enough.  this method
	 * runs the given query using the criteria and returns true if it worked
	 * and false otherwise.  note:  this will not return the results of a
	 * SELECT query; it's meant more for things like an INSERT INTO SELECT FROM
	 * style query that won't quite work using the insert method above.
	 */
	public function runQuery(string $query, array $criteria = []): bool;
	
	/**
	 * @param string $query
	 * @param array $criteria
	 * @return string
	 *
	 * uses the profiling/logging capabilities within Aura/Sql to get the most
	 * recent query
	 */
	public function getStatement(string $query, array $criteria = []): string;
}
