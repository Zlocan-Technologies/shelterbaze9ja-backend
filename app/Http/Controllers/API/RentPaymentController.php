<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\CancelRentalRequest;
use App\Http\Requests\Rent\EarlyTerminationRequest;
use App\Http\Requests\Rent\GenerateInvoiceRequest;
use App\Http\Requests\Rent\RenewalRequest;
use App\Http\Requests\Rent\ReportIssueRequest;
use App\Http\Requests\Rent\UploadPaymentProofRequest;
use App\Repositories\RentPaymentRepository;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;

class RentPaymentController extends Controller
{

    public function __construct(private RentPaymentRepository $rentPaymentRepository) {}

    /**
     * Generate invoice for rent payment
     *  
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInvoice(GenerateInvoiceRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->generateInvoice($request));
    }

    /**
     * Upload payment proof for rent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPaymentProof(UploadPaymentProofRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->uploadPaymentProof($request));
    }

    /**
     * Get payment history for authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentHistory(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getPaymentHistory($request));
    }

    /**
     * Get user's rented apartments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyApartments(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getMyApartments($request));
    }

    /**
     * Report an issue for rented apartment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportIssue(ReportIssueRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->reportIssue($request));
    }

    /**
     * Get bank details for rent payments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankDetails(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getBankDetails($request));
    }

    /**
     * Get rental agreement details
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRentalAgreement($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getRentalAgreement($id, $request));
    }

    /**
     * Request lease renewal
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestRenewal(RenewalRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->requestRenewal($request));
    }

    /**
     * Get payment receipt
     * 
     * @param int $paymentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentReceipt($paymentId, Request $request)
    {
       return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getPaymentReceipt($paymentId, $request));
    }

    /**
     * Get rent payment summary and analytics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSummary(Request $request)
    {
       return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getPaymentSummary($request));
    }

    /**
     * Cancel rental agreement (before payment verification)
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRentalRequest($id, CancelRentalRequest $request)
    {
       return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->cancelRentalRequest($id, $request));
    }

    /**
     * Request early lease termination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEarlyTermination(EarlyTerminationRequest $request)
    {
      return (new ResponseHandler())->executeTransaction(fn() => $this->rentPaymentRepository->requestEarlyTermination($request));
    }

    /**
     * Get rental insights and recommendations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRentalInsights(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->getRentalInsights($request));
    }

    /**
     * Export rental data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportRentalData(Request $request)
    {
       return (new ResponseHandler())->execute(fn() => $this->rentPaymentRepository->exportRentalData($request));
    }
}
