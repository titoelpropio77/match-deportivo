<?php

namespace App\Http\Requests\Reservations;

use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walk-in or phone court booking registered by venue staff, optionally with rented gear.
 */
class StoreReservationRequest extends FormRequest
{
    /**
     * Longest booking staff can register at once.
     */
    public const MAX_HOURS = 4;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_field_id' => ['required', 'integer', 'exists:court_fields,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'hours' => ['required', 'integer', 'between:1,'.self::MAX_HOURS],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'customer_name' => ['required_without:user_email', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment' => ['required', Rule::in(['venue', ...array_keys(CourtReservation::PAYMENT_METHODS)])],
            'notes' => ['nullable', 'string', 'max:1000'],
            // rental_item_id => quantity (0 = not rented).
            'rentals' => ['sometimes', 'array'],
            'rentals.*' => ['nullable', 'integer', 'between:0,20'],
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
            'court_field_id' => 'cancha',
            'sport_id' => 'deporte',
            'date' => 'fecha',
            'start_time' => 'hora de inicio',
            'hours' => 'horas',
            'user_email' => 'email del cliente',
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'payment' => 'pago',
            'notes' => 'notas',
        ];
    }
}
