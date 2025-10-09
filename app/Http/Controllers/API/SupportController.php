<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\HelpCenterFaq;
use App\Models\SystemSetting;
use App\Util\ApiResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    //
    public function getFaqs()
    {
        $faqs = HelpCenterFaq::latest()->get();
        $systemSettings = SystemSetting::where('key','like', 'support_%')->get();

        return ApiResponse::respond(
            message: 'Success!',
            data: [
                'faqs' => $faqs,
                'systemSettings' => $systemSettings
            ]
        );
    }


    public function getAmenities() {
        $amenities = Amenity::latest()->get();
        return ApiResponse::respond(
            message: 'Success!',
            data: $amenities
        );
    }
}
