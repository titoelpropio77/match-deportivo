<?php

namespace App\Http\Requests\EventReservations;

use App\Enums\EventKind;
use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walk-in or phone event space booking registered by venue staff.
 */
class StoreEventReservationRequest extends FormRequest
{
    public const MAX_HOURS = 12;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_space_id' => ['required', 'integer', 'exists:event_spaces,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,'.self::MAX_HOURS],
            'guests' => ['required', 'integer', 'min:1'],
            'event_type' => ['nullable', Rule::enum(EventKind::class)],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'customer_name' => ['required_without:user_email', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment' => ['required', Rule::in(['venue', ...array_keys(CourtReservation::PAYMENT_METHODS)])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_email.exists' => 'No hay ningún usuario de la app con ese email.',
            'customer_name.required_without' => 'Indica el nombre del cliente o el email de su cuenta en la app.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_space_id' => 'espacio',
            'date' => 'fecha',
            'start_time' => 'hora de inicio',
            'hours' => 'horas',
            'guests' => 'personas',
            'event_type' => 'tipo de evento',
            'user_email' => 'email del cliente',
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'payment' => 'pago',
            'notes' => 'notas',
        ];
    }
}
