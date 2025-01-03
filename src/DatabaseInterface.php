<?php

namespace Dashifen\Database;

interface DatabaseInterface
{
  /**
   * Returns true if the object is connected to the database; false otherwise.
   *
   * @return bool
   */
  public function isConnected(): bool;
  
  /**
   * Returns an array of the column names within $table.
   *
   * @param string $table
   *
   * @return string[]
   * @throws DatabaseException
   */
  public function getTableColumns(string $table): array;
  
  /**
   * Returns the name of the database to which we're connected or null.
   *
   * @return null|string
   */
  public function getDatabase(): ?string;
  
  /**
   * Returns the ID of the most recently inserted row.  The name parameter is
   * unlikely to be necessary, but some database systems (e.g. PostgresSQL)
   * need it.
   *
   * @param string|null $name
   *
   * @return int
   */
  public function getInsertedId(?string $name = null): int;
  
  /**
   * Returns a map of PDO error code to message or null.
   *
   * @return array|null
   */
  public function getError(): ?array;
  
  /**
   * Given a query, returns the first column of the first row or null.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return mixed
   * @throws DatabaseException
   */
  public function getVar(string $query, array $criteria = []): mixed;
  
  /**
   * Given a query, returns all the values in the first column for all rows or
   * an empty array.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getCol(string $query, array $criteria = []): array;
  
  /**
   * Given a query, returns all columns of the first row returned or an empty
   * array.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getRow(string $query, array $criteria = []): array;
  
  /**
   * Given a query, returns an array indexed by the first column and
   * containing the subsequent columns as values or an empty array.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getMap(string $query, array $criteria = []): array;
  
  /**
   * Given a query, returns all results selected or an empty array.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getResults(string $query, array $criteria = []): array;
  
  /**
   * Inserts $values into $table returning the inserted ID if $values
   * represents one row or the number of inserted rows if it has more than one.
   *
   * @param string $table
   * @param array  $values
   *
   * @return int
   * @throws DatabaseException
   */
  public function insert(string $table, array $values): int;
  
  /**
   * Updates $values within $table based on $criteria returning the number of
   * rows changed by the update (including zero).
   *
   * @param string $table
   * @param array  $values
   * @param array  $criteria
   *
   * @return int
   * @throws DatabaseException
   */
  public function update(string $table, array $values, array $criteria = []): int;
  
  /**
   * Deletes from $table based on the $criteria returning the number of rows
   * deleted (including zero).
   *
   * @param string $table
   * @param array  $criteria
   *
   * @return int
   * @throws DatabaseException
   */
  public function delete(string $table, array $criteria = []): int;
  
  /**
   * Executes the $query using $criteria returning true if it worked and false
   * otherwise.  Useful for queries that don't conform to the above methods
   * like INSERT INTO ... SELECT FROM queries.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return bool
   * @throws DatabaseException
   */
  public function runQuery(string $query, array $criteria = []): bool;
  
  /**
   * Uses the profile/logging capabilities within Aura/Sql to get the most
   * recent query.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return string
   */
  public function getStatement(string $query, array $criteria = []): string;
}
