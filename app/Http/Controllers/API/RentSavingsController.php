<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RentSaving\CreateRentSavingRequest;
use App\Http\Requests\RentSaving\DepositSavingRequest;
use App\Http\Requests\RentSaving\PausePlanRequest;
use App\Http\Requests\RentSaving\UpdateRentSavingRequest;
use App\Http\Requests\RentSaving\VerifyDepositRequest;
use App\Http\Requests\RentSaving\WithdrawSavingRequest;
use App\Repositories\RentSavingRepository;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;

class RentSavingsController extends Controller
{
    public function __construct(
        private RentSavingRepository $rentSavingRepository
    ) {}

    /**
     * Display a listing of user's savings plans
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->index($request));
    }

    /**
     * Store a newly created savings plan
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateRentSavingRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->create($request));
    }

    /**
     * Display the specified savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->find($request, $id));
    }

    /**
     * Update the specified savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, UpdateRentSavingRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->update($request, $id));
    }

    /**
     * Initiate a deposit to savings plan
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(DepositSavingRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->depositSaving($request));
    }

    /**
     * Verify deposit payment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyDeposit(VerifyDepositRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->verifyDeposit($request));
    }

    /**
     * Request withdrawal from savings plan
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(WithdrawSavingRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->withdrawSaving($request));
    }

    /**
     * Get transaction history for a savings plan
     * 
     * @param int $savingsId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionHistory($savingsId, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->getTransactionHistory($request, $savingsId));
    }

    /**
     * Get savings dashboard overview
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->dashboard($request));
    }

    /**
     * Cancel a savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPlan($id, Request $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->cancelPlan($request, $id));
    }

    /**
     * Get savings plan statistics
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->getStatistics($request, $id));
    }

    /**
     * Pause a savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pausePlan(PausePlanRequest $request, $id)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->rentSavingRepository->pausePlan($request, $id));
    }

    /**
     * Resume a paused savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumePlan($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->resumePlan($request, $id));
    }

    /**
     * Get savings insights and recommendations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInsights(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->getInsights($request));
    }


    /**
     * Export savings data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportData(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->rentSavingRepository->exportData($request));
    }
}
