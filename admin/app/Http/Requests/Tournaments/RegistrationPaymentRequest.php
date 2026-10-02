<?php

namespace App\Http\Requests\Tournaments;

use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entry fee collected at the venue for a pending team registration.
 */
class RegistrationPaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(array_keys(CourtReservation::PAYMENT_METHODS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['payment_method' => 'método de pago'];
    }
}
