<?php

namespace App\Enums;

enum WithdrawalStatus: string {
    case PENDING = "PENDING"; 
    case PROCESSING = "PROCESSING"; 
    case PAID = "PAID"; 
    case REJECTED = "REJECTED";

    public static function toArray(): array {
        return [
            self::PENDING->value,
            self::PROCESSING->value,
            self::PAID->value,
            self::REJECTED->value,
        ];
    }

    public static function toKeyValue(): array {
        return [
            self::PENDING->value => 'Pending',
            self::PROCESSING->value => 'Processing',
            self::PAID->value => 'Paid',
            self::REJECTED->value => 'Rejected',
        ];
    }
}