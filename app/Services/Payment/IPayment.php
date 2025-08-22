<?php


namespace App\Services\Payment;


interface IPayment
{

    public function generatePaymentReference($prefix = "");

    public function initializePayment(float $amount, string $email, string $reference, array $metadata = []);

    public function verifyPayment(string $reference);
}
