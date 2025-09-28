<?php
// =============================================================================
// FILE: app/Http/Controllers/API/AgentController.php
// =============================================================================
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\VerifyPropertyRequest;
use App\Models\User;
use App\Models\AgentAssignment;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\RentalAgreement;
use App\Models\AuditLog;
use App\Repositories\AgentRepository;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgentController extends Controller
{

    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService,
        private AgentRepository $agentRepository
    ) {}


    /**
     * Get landlords assigned to the authenticated agent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedLandlords(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getAssignedLandlords($request);
        });
    }

    /**
     * Get properties assigned to the agent for verification
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedProperties(Request $request)
    {
       return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getAssignedProperties($request);
        });
    }

    /**
     * Verify a property
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyProperty(VerifyPropertyRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(function () use ($request) {
            return $this->agentRepository->verifyProperty($request);
        });
    }

    /**
     * Verify an agent using their ID
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyAgent(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->verifyAgent($request);
        });
    }

    /**
     * Manage listing on behalf of landlord
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function manageListingForLandlord(Request $request)
    {
        return (new ResponseHandler())->executeTransaction(function () use ($request) {
            return $this->agentRepository->manageListingForLandlord($request);
        });
    }

    /**
     * Get verification history for the agent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVerificationHistory(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getVerificationHistory($request);
        });
    }

    /**
     * Get agent performance statistics and dashboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentStats(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getAgentStats($request);
        });
    }

    /**
     * Update agent availability status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAgentAvailability(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->updateAgentAvailability($request);
        });
    }

    /**
     * Get agent's commission and earnings summary
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentEarnings(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getAgentEarnings($request);
        });
    }

    /**
     * Submit a report or feedback
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitAgentReport(Request $request)
    {
        return (new ResponseHandler())->executeTransaction(function () use ($request) {
            return $this->agentRepository->submitAgentReport($request);
        });
    }

    /**
     * Get agent training resources and progress
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentTrainingResources(Request $request)
    {
        return (new ResponseHandler())->execute(function () use ($request) {
            return $this->agentRepository->getAgentTrainingResources($request);
        });
    }


}
