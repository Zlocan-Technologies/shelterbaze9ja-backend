<?php


namespace App\Services\Payment;


interface IPayment
{

    public function generatePaymentReference($prefix = "");

    public function initializePayment(float $amount, string $email, string $reference, array $metadata = [], string $callback = "");

    public function verifyPayment(string $reference);
}
