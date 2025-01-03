<?php

namespace Dashifen\Database\MySQL;

use Dashifen\Database\DatabaseInterface;

/**
 * Interface MysqlInterface
 *
 * @package Dashifen\Database\Mysql
 */
interface MysqlInterface extends DatabaseInterface
{
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
  public function upsert(string $table, array $values, array $updates): int;
  
  /**
   * Returns the list of enumerated values within a MySQL ENUM column.
   *
   * @param string $table
   * @param string $column
   *
   * @return array
   * @throws MysqlException
   */
  public function getEnumValues(string $table, string $column): array;
}
