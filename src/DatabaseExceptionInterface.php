<?php

namespace Dashifen\Database;

/**
 * Interface DatabaseExceptionInterface
 *
 * @package Dashifen\Database
 */
interface DatabaseExceptionInterface {
	/**
	 * @return string
	 */
	public function getQuery(): string;
	
	/**
	 * @param string $query
	 */
	public function setQuery(string $query): void;
}
