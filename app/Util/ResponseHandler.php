<?php

namespace App\Util;

use Illuminate\Support\Facades\DB;
use Throwable;

class ResponseHandler
{
    use ErrorHandler;

    //use this method for simple operations that do not require a transaction
    public function execute(callable $function)
    {
        try {
            return $function();
        } catch (Throwable $th) {
            return $this->throwableErrorHandler($th, $th->getMessage());
        }
    }

    //use this method when you want to wrap multiple DB operations in a transaction
    public function executeTransaction(callable $function)
    {
        DB::beginTransaction();
        try {
            $result = $function();
            DB::commit();
            return $result;
        } catch (Throwable $th) {
            DB::rollBack();
            return $this->throwableErrorHandler($th, $th->getMessage());
        }
    }
}
