<?php

namespace App\Http\Requests\Reservations;

use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payment collected at the venue (cash, transfer or QR). Used for court, event space and tournament payments.
 */
class RegisterPaymentRequest extends FormRequest
{
    protected $errorBag = 'payment';

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
