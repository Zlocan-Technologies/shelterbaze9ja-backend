<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\CreatePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Requests\Property\UploadMediaRequest;
use App\Repositories\PropertyRepository;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;

class PropertyController extends Controller
{

    public function __construct(
        private PropertyRepository $propertyRepository
    ) {}

    public function index(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->getAllProperties($request));
    }

    public function show(Request $request, $id)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->getPropertyById($request, $id));
    }

    public function store(CreatePropertyRequest $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->createProperty($request));
    }

    public function update(UpdatePropertyRequest $request, $id)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->updateProperty($request, $id));
    }

    public function destroy(Request $request, $id)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->deleteProperty($request, $id));
    }

    public function myListings(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->myListings($request));
    }

    public function uploadMedia(UploadMediaRequest $request, $id)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->uploadMedia($request, $id));
    }

    public function removeMedia($mediaId, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->removeMedia($request, $mediaId));
    }

    public function toggleStatus($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->toggleStatus($request, $id));
    }

    public function toggleFavorite($id, Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->toggleFavorite($request, $id));
    }

    public function getFavorites(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->getUserFavoriteProperties($request));
    }

    public function getBookedApartments(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->propertyRepository->getBookedApartments($request));
    }
}
