<?php
/** @noinspection SqlNoDataSourceInspection */

namespace Dashifen\Database;

use PDOException;
use Aura\Sql\ExtendedPdo;
use Aura\Sql\Exception\CannotBindValue;
use Aura\Sql\Profiler\ProfilerInterface;

abstract class AbstractDatabase implements DatabaseInterface
{
  protected string $database;
  protected string $columnPrefix = "";
  protected string $columnSuffix = "";
  protected ExtendedPdo $db;
  
  /**
   * Database constructor.
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
    ?string $username = null,
    ?string $password = null,
    array $options = [],
    array $queries = [],
    ?ProfilerInterface $profiler = null
  ) {
    if ($this->db->isConnected()) {
      $this->db->disconnect();
    }
    
    $this->db = new ExtendedPdo($dsn, $username, $password, $options,
      $queries, $profiler);
    
    $this->db->connect();
    if (!$this->db->isConnected()) {
      throw new DatabaseException('Unable to connect to database.');
    }
    
    $parts = explode("=", $dsn);
    $this->database = array_pop($parts);
  }
  
  /**
   * Returns true if the object is connected to the database; false otherwise.
   *
   * @return bool
   */
  public function isConnected(): bool
  {
    return $this->db->isConnected();
  }
  
  /**
   * Returns an array of the column names within $table.
   *
   * @param string $table
   *
   * @return array
   * @throws DatabaseException
   */
  public function getTableColumns(string $table): array
  {
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
      return $this->getCol($query, $params);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $params));
    }
  }
  
  /**
   * Returns the name of the database to which we're connected or null.
   *
   * @return null|string
   */
  public function getDatabase(): ?string
  {
    return $this->isConnected() ? $this->database : null;
  }
  
  /**
   * returns the ID of the most recently inserted row; name is unlikely
   * to be necessary, according to the Aura\Sql docs, but it's important
   * for some DB systems, e.g. PostgresSQL.
   *
   * @param string|null $name
   *
   * @return int
   */
  public function getInsertedId(?string $name = null): int
  {
    return $this->db->lastInsertId($name);
  }
  
  /**
   * Returns a map of PDO error code to message or null.
   *
   * @return array|null
   */
  public function getError(): ?array
  {
    return !is_null($errorCode = $this->db->errorCode())
      ? [$errorCode => $this->db->errorInfo()]
      : null;
  }
  
  /**
   * Given a query, returns the first column of the first row or null.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return mixed|null
   * @throws DatabaseException
   */
  public function getVar(string $query, array $criteria = []): mixed
  {
    try {
      return $this->db->fetchValue($query, $criteria);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    }
  }
  
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
  public function getCol(string $query, array $criteria = []): array
  {
    try {
      return $this->db->fetchCol($query, $criteria);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    }
  }
  
  /**
   * given a query, returns all columns of the first row returned.
   * returns an empty array if nothing is selected or nothing could
   * be selected.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getRow(string $query, array $criteria = []): array
  {
    try {
      
      // unlike some of the above fetch* methods of the ExtendedPdo object,
      // this one can return an array or false.  since we only want to return
      // arrays, we'll test the return value first and return empty ones when
      // we need to.
      
      $results = $this->db->fetchOne($query, $criteria);
      return is_array($results) ? $results : [];
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    }
  }
  
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
  public function getMap(string $query, array $criteria = []): array
  {
    try {
      $map = [];
      foreach ($this->db->yieldAll($query, $criteria) as $result) {
        $key = array_shift($result);
        
        // if there's only one item left in our result, we map the above key to
        // it directly.  otherwise, we do nothing here and map the key to the
        // remaining column values as an array.
        
        $map[$key] = sizeof($result) === 1
          ? array_shift($result)
          : $result;
      }
      
      return $map;
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    }
  }
  
  /**
   * Given a query, returns all results selected or an empty array.
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return array
   * @throws DatabaseException
   */
  public function getResults(string $query, array $criteria = []): array
  {
    try {
      return $this->db->fetchAll($query, $criteria);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    }
  }
  
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
  public function insert(string $table, array $values): int
  {
    try {
      $inserted = isset($values[0]) && is_array($values[0])
        ? $this->insertMultiple($table, $values)
        : $this->insertSingle($table, $values);
      
      return !is_numeric($inserted) ? sizeof($inserted) : $inserted;
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($table, $values));
    }
  }
  
  /**
   * Inserts the multiple rows contained in $values into the specified table.
   *
   * @param string $table
   * @param array  $values
   *
   * @return mixed|null
   * @throws DatabaseException
   */
  protected function insertMultiple(string $table, array $values): ?array
  {
    $columns = array_keys($values[0]);
    if (!$this->verifyColumns($columns, $values)) {
      return null;
    }
    
    // we need a separate parenthetical VALUES clause for each of the rows
    // contained in $values.  the first statement below creates it. then, we
    // take each of those statements and create a series of them based on the
    // number of values we're going to insert using the same method.
    
    $parenthetical = $this->placeholders(sizeof($columns));
    $parentheticals = $this->placeholders(sizeof($values), $parenthetical, false);
    $columns = join(', ', $columns);
    
    $statement = "INSERT INTO $table ($columns) VALUES $parentheticals";
    return $this->insertExecute($statement, $this->mergeBindings($values));
  }
  
  /**
   * Ensures that the specified set of columns and values could be used to
   * produce a viable INSERT statement throwing DatabaseExceptions if they
   * can't.
   *
   * @param array $columns
   * @param array $values
   *
   * @return true
   * @throws DatabaseException
   */
  protected function verifyColumns(array $columns, array $values): true
  {
    $columnCount = sizeof($columns);
    foreach ($values as $value) {
      if (sizeof($value) !== $columnCount) {
        
        // if the number of values we want to insert is not hte same as the
        // number of columns into which we'll be inserting them, we won't be
        // able to proceed.
        
        throw new DatabaseException('insertMultiple failed: mismatched column counts.');
      }
      
      if (sizeof(array_diff($columns, array_keys($value))) !== 0) {
        
        // also, if the columns into which those values are going to be
        // inserted don't match the names of the columns that were sent here
        // from the calling scope, we won't proceed.  this means that we can't
        // insert an ID and an email address with one row and an ID and a phone
        // number in another one.
        
        throw new DatabaseException("insertMultiple failed: mismatched columns");
      }
    }
    
    return true;
  }
  
  /**
   * Returns a string appropriate for use within a statement as the
   * placeholders for a series of bound values.  e.g., for a count of 3,
   * returns (?, ?, ?).
   *
   * @param int    $count
   * @param string $placeholder
   * @param bool   $surround
   *
   * @return string
   */
  protected function placeholders(int $count, string $placeholder = '?', bool $surround = true): string
  {
    // typically, once we create our string of placeholders, we want to
    // surround it with parentheses, but sometimes maybe we don't.  the
    // $surround bool will tell us how to proceed.
    
    $placeholders = join(', ', array_pad([], $count, $placeholder));
    return $surround ? "($placeholders)" : $placeholders;
  }
  
  /**
   * Executes an INSERT statement.
   *
   * @param string $statement
   * @param array  $values
   *
   * @return array|null
   */
  protected function insertExecute(string $statement, array $values): ?array
  {
    // our insert method should return either a single ID or a list of created
    // IDs.  we can use the ExtendedPdo's fetchAffected method to determine how
    // many were inserted and then use that to determine our return value.  if
    // nothing at all was inserted, we return null.
    
    $affected = $this->db->fetchAffected($statement, array_values($values));
    
    return match ($affected) {
      0       => null,
      1       => [$this->getInsertedId()],
      default => $this->getInsertedIds($affected),
    };
  }
  
  /**
   * Returns an array of inserted IDs.
   *
   * @param int $affected
   *
   * @return array
   */
  protected function getInsertedIds(int $affected): array
  {
    // the getInsertedId method give us the most recently inserted one.  since
    // databases insert IDs sequentially, if we inserted X rows, and we know
    // the last one, we can extrapolate the rest of them as follows:
    
    $lastId = $this->getInsertedId();
    $firstId = $lastId - ($affected - 1);
    return range($lastId, $firstId);
  }
  
  /**
   * Flattens the arrays passed here from the calling scope.
   *
   * @param array ...$arrays
   *
   * @return array
   */
  protected function mergeBindings(array ...$arrays): array
  {
    // at first blush, it seems like one could use something like array_merge
    // to build our return value, but because that method would overwrite
    // matching keys in early arrays with values in the latter ones, we can't
    // rely on it.  instead, what we actually want to do is flatten our arrays
    // which we've learned to do here: http://stackoverflow.com/a/1320156/360838.
    
    $bindings = [];
    array_walk_recursive($arrays, function ($x) use (&$bindings) {
      $bindings[] = $x;
    });
    
    return $bindings;
  }
  
  /**
   * Returns the results of a query that inserts a single row into the
   * database.
   *
   * @param string $table
   * @param array  $values
   *
   * @return mixed|null
   */
  protected function insertSingle(string $table, array $values): ?array
  {
    // this one is far less complex that our insertMultiple above.  we'll build
    // our statement and then pass control over to insertExecute which will
    // actually perform our query and return the appropriate results up the
    // call stack.
    
    return $this->insertExecute($this->insertBuild($table, $values), $values);
  }
  
  /**
   * Builds an INSERT statement from our parameters and returns it.  Separated
   * from the prior method so that our MySQL implementation can use it when
   * building "upsert" queries.
   *
   * @param string $table
   * @param array  $values
   *
   * @return string
   */
  protected function insertBuild(string $table, array $values): string
  {
    // the keys of our values array are our columns.  we need to surround them
    // with our column suffix and prefix and create a comma-separated list of
    // them.  for example, we take [ID, email, phone] and produce something
    // like `ID`, `email`, `phone` assuming the column suffix and prefix are
    // backticks.
    
    $columns = array_keys($values);
    $separator = $this->columnSuffix . ', ' . $this->columnPrefix;
    $columns = $this->columnPrefix . join($separator, $columns) . $this->columnSuffix;
    $params = [$table, $columns, $this->placeholders(sizeof($values))];
    return vsprintf('INSERT INTO %s (%s) VALUES %s', $params);
  }
  
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
  public function update(string $table, array $values, array $criteria = []): int
  {
    $statement = "UPDATE $table SET " . $this->getColumnBindingClause($values);
    
    if (sizeof($criteria) > 0) {
      $statement .= ' WHERE ' . $this->getColumnBindingClause($criteria);
    }
    
    // our mergeBindings method above is variadic and can flatten multiple
    // arguments into one single array of bindings.  therefore, we can send it
    // both our values and criteria arrays and get back exactly what we need.
    
    $bindings = $this->mergeBindings($values, $criteria);
    
    try {
      return $this->db->fetchAffected($statement, $bindings);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($statement, $bindings));
    }
  }
  
  /**
   * Returns a comma separated list of the columns surrounded by our column
   * prefix and suffix and prepared to be used when binding query parameters.
   *
   * @param array $columns
   *
   * @return string
   */
  protected function getColumnBindingClause(array $columns): string
  {
    foreach ($columns as $column) {
      $clause[] = sprintf("%s%s%s = ?", $this->columnPrefix, $column,
        $this->columnSuffix);
    }
    
    return join(', ', $clause ?? []);
  }
  
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
  public function delete(string $table, array $criteria = []): int
  {
    $bindings = array_values($criteria);
    $statement = "DELETE FROM $table";
    
    if (sizeof($criteria) > 0) {
      $statement = ' WHERE ' . $this->getColumnBindingClause(array_keys($criteria));
    }
    
    try {
      return $this->db->fetchAffected($statement, $bindings);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($statement, $bindings));
    }
  }
  
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
  public function runQuery(string $query, array $criteria = []): bool
  {
    try {
      $pdoStatement = $this->db->perform($query, $criteria);
    } catch (PDOException $e) {
      throw new DatabaseException($e, $this->getStatement($query, $criteria));
    } catch (CannotBindValue $e) {
      throw new DatabaseException($e);
    }
    
    // the errorCode method of our PDO statement object returns a SQLSTATE code
    // identifying any problems that cropped up during that execution of our
    // query.  the code 00000 indicates that there were no such problems, and
    // we'll use that to return a Boolean value rather than the actual code.
    
    $errorCode = $pdoStatement->errorCode();
    return $errorCode == "00000";
  }
  
  /**
   * Uses the profile/logging capabilities within Aura/Sql to get the most
   * recent query.
   *
   * @source: http://stackoverflow.com/a/12015992/360838 (accessed 2025-01-03)
   *
   * @param string $query
   * @param array  $criteria
   *
   * @return string
   */
  public function getStatement(string $query, array $criteria = []): string
  {
    // the ExtendedPdo object uses its parser to manipulate the criteria it
    // receives to do additional tasks like handling arrays for IN () clauses.
    // to try and get as close to the statement that is run against the
    // database as possible, we'll do that here, too.
    
    $parser = $this->db->getParser();
    [$query, $criteria] = $parser->rebuild($query, $criteria);
    
    // now, we'll use the (slightly modified) code from stack overflow
    // (referenced above) to builds a string version of the statement.
    
    $keys = [];
    foreach ($criteria as $key => $value) {
      $keys[] = is_string($key) ? "/:$key/" : '/[?]/';
      
      if (is_array($value)) {
        $criteria[$key] = implode(',', $value);
      }
      
      if (is_null($value)) {
        $criteria[$key] = 'NULL';
      }
    }
    
    return preg_replace($keys, $criteria, $query);
  }
}
