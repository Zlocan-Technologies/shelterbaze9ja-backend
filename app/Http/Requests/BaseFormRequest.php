<?php

namespace App\Http\Requests;

use App\Util\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;


class BaseFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    //handle error validations
    protected function failedValidation(Validator $validator)
    {
        $response = ApiResponse::respond(
            data: null,
            message: $validator->errors()->first(),
            status: false,
            statusCode: 422,
            error: $validator->errors()->first(),
            errors: $validator->errors()->all()
        );

        throw new HttpResponseException($response);
    }

    // protected function prepareForValidation(): void
    // {
    //     // Check if there's an encrypted payload
    //     if ($this->has('payload')) {
    //         try {
    //             // Decrypt the payload
    //             $decrypted = Crypt::decryptString($this->input('payload'));

    //             // Convert JSON string to array (assuming encrypted string is JSON)
    //             $data = json_decode($decrypted, true);

    //             if (is_array($data)) {
    //                 // Merge decrypted data into the request so validation can use it
    //                 $this->merge($data);
    //             }
    //         } catch (\Exception $e) {
    //             throw new HttpResponseException(ApiResponse::respond(
    //                 data: null,
    //                 message: 'Invalid encrypted request payload.',
    //                 status: false,
    //                 statusCode: 400,
    //                 error: 'Decryption failed',
    //                 errors: []
    //             ));
    //         }
    //     }
    // }
}
