<?php

namespace Dashifen\Database;

use Throwable;
use Dashifen\Exception\Exception as Exception;

class DatabaseException extends Exception
{
  public function __construct(string|Throwable $x, protected(set) string $query = "")
  {
    // to try and make things a little easier on the calling scope, we receive
    // either a string or an exception as our first parameter.  based on which
    // it is, we can prepare the arguments that we send to our parent's
    // constructor.
    
    $args = is_a($x, Throwable::class)
      ? [$x->getMessage(), $x->getCode(), $x]
      : [$x, 0, null];
    
    parent::__construct(...$args);
  }
}
