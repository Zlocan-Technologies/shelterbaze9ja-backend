<?php

namespace App\Util;

use Throwable;

class ResponseHandler
{
    use ErrorHandler;

   public function execute(callable $function)
   {
       try {
          return $function();
       } catch (Throwable $th) {
          return $this->throwableErrorHandler($th, $th->getMessage());
       }
   }
}
