<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancellation by the venue; the reason is shown to the customer in the app.
 * Used for court, event space and tournament registrations.
 */
class CancelReservationRequest extends FormRequest
{
    protected $errorBag = 'cancel';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cancellation_reason' => 'motivo de anulación'];
    }
}
