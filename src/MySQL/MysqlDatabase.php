<?php
/** @noinspection SqlNoDataSourceInspection */

namespace Dashifen\Database\MySQL;

use PDOException;
use Dashifen\Database\AbstractDatabase;
use Dashifen\Database\DatabaseException;
use Aura\Sql\Profiler\ProfilerInterface;

/**
 * Class MysqlDatabase
 *
 * @package Dashifen\Database\MySQL\MysqlDatabase
 */
class MysqlDatabase extends AbstractDatabase implements MysqlInterface
{
  /**
   * MysqlDatabase constructor.
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
    parent::__construct($dsn, $username, $password, $options, $queries, $profiler);
    $this->columnPrefix = "`";
    $this->columnSuffix = "`";
  }
  
  /**
   * Builds and executes an INSERT INTO ... ON DUPLICATE KEY UPDATE query.
   *
   * @param string $table
   * @param array  $values
   * @param array  $updates
   *
   * @return int
   * @throws MysqlException
   */
  public function upsert(string $table, array $values, array $updates): int
  {
    $insert = $this->insertBuild($table, $values) . " ON DUPLICATE KEY UPDATE "
      . $this->getColumnBindingClause(array_keys($updates));
    
    $bindings = $this->mergeBindings($values, $updates);
    
    try {
      return $this->db->fetchAffected($insert, $bindings);
    } catch (PDOException $e) {
      throw new MysqlException($e, $this->getStatement($insert, $bindings));
    }
  }
  
  /**
   * Returns the list of enumerated values within a MySQL ENUM column.
   *
   * @param string $table
   * @param string $column
   *
   * @return array
   * @throws MysqlException
   */
  public function getEnumValues(string $table, string $column): array
  {
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
    } catch (DatabaseException $e) {
      throw new MysqlException($e, $this->getStatement($query, $params));
    }
    
    if (str_starts_with($type, "enum(") || str_starts_with($type, "set(")) {
      
      // if what we selected was an enum or set field, then the following
      // regular expression will match each of the values within the selected
      // column type.  preg_match_all puts each of those matches into the
      // first index of the $matches array, and so that's what we return here.
      
      preg_match_all("/'([^']+)'/", $type, $matches);
      return $matches[1];
    } else {
      throw new MysqlException("getEnumValues failed: type mismatch");
    }
  }
}
