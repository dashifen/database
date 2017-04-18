<?php

namespace Dashifen\Database;

class DatabaseException extends \Exception implements DatabaseExceptionInterface {
	
	/**
	 * @var string $query
	 */
	protected $query;
	
	/**
	 * @return string
	 */
	public function getQuery(): string {
		return $this->query;
	}
	
	/**
	 * @param string $query
	 */
	public function setQuery(string $query): void {
		$this->query = $query;
	}
}
