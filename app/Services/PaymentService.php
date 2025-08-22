<?php

namespace App\Services;

use App\Services\Payment\IPayment;
use App\Traits\GenerateReference;
use Illuminate\Support\Facades\Http;
use \Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentService implements IPayment
{
    use GenerateReference;

    private $baseUrl;
    private $secretKey;
    private $callbackUrl;

    public function __construct()
    {
        $this->setBaseUrl();
        $this->setKey();
    }


    public function setKey()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function setCallbackUrl($url)
    {
        $this->callbackUrl = $url;
    }

    public function getCallbackUrl()
    {
        return $this->callbackUrl;
    }

    public function setBaseUrl()
    {
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co/');
    }

    // public function fundWalletCallBack($user_id)
    // {
    //     return url($this->fundWalletCallbackUrl . $user_id);
    // }

    // public function fundBookingCallBack()
    // {
    //     Log::error("booking callback url: " . url($this->fundBookingCallbackUrl));
    //     return url($this->fundBookingCallbackUrl);
    // }



    //initiate transaction and get checkout url
    public function initializePayment(float $amount, string $email, string $reference, array $metadata = [])
    {
        $url = $this->baseUrl . 'transaction/initialize';
        $response = Http::acceptJson()->withToken($this->secretKey)->post($url, [
            'email' => $email,
            'amount' => (int)$amount * 100,
            'callback_url' => $this->getCallbackUrl(),
            'channels' => [
                'card',
                // 'bank',
                // 'ussd',
                'bank_transfer',
                'qr',
                'mobile_money',
            ],
            'metadata' => json_encode($metadata),
            'reference' => $reference,
        ]);

        return $response->json();
    }

    //verify a transaction
    public function verifyPayment(string $reference)
    {
        $url = $this->baseUrl . 'transaction/verify/' . $reference;
        $response = Http::acceptJson()->withToken($this->secretKey)->get($url);

        return $response;
    }

    public function fetchBanks()
    {
        $url = $this->baseUrl . 'bank';
        $response = Http::acceptJson()->withToken($this->secretKey)
            ->get($url, [
                'currency' => "NGN"
            ]);
        return $response->json();
    }

    public function resolveAccountNumber($bankName, $accountNumber)
    {
        $banks = $this->fetchBanks()['data'];

        // Find the bank by name (case-insensitive)
        $bankData = collect($banks)->first(function ($item) use ($bankName) {
            return str_contains(strtolower($item['name']), strtolower($bankName));
        });

        // If bank not found, return error or null
        if (!$bankData || !isset($bankData['code'])) {
            return [
                'status' => false,
                'message' => 'Bank details could not be verified',
            ];
        }

        $bankCode = $bankData['code'];

        // Call Paystack API to resolve account number
        $url = $this->baseUrl . 'bank/resolve';
        $response = Http::acceptJson()->withToken($this->secretKey)
            ->get($url, [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        if ($response->successful()) {
            return [
                'status' => true,
                'data' => $response->json()['data'],
            ];
        }

        return [
            'status' => false,
            'message' => match ($response->json()['type']) {
                'validation_error' => 'Unable to resolve your account details, please check your details.',
                 default => 'Failed to resolve account number',
            },
        ];
    }

    public function generatePaymentReference($prefix = "") {
        return $this->generateReference(prefix: $prefix);
    }
}
