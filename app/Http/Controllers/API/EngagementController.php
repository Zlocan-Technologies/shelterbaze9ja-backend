<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Engatement\InitiatePaymentRequest;
use App\Http\Requests\Engatement\VerifyPaymentRequest;
use App\Models\Property;
use App\Models\EngagementFee;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Repositories\EngagementRepository;
use App\Services\PaymentService;
use App\Services\NotificationService;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EngagementController extends Controller
{
    public function __construct(private EngagementRepository $engagementRepository) {}

    public function initiatePayment(InitiatePaymentRequest $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->engagementRepository->initiateEngagement($request));
    }

    public function verifyPayment(VerifyPaymentRequest $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->engagementRepository->verifyPayment($request));
    }

    public function getPropertyContact($propertyId, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->engagementRepository->getPropertyContact($request, $propertyId));
    }

    public function myEngagements(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->engagementRepository->myEngagements($request));
    }

    public function getInterestedTenants($propertyId, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->engagementRepository->getInterestedTenants($request, $propertyId));
    }
}
