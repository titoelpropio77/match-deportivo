<?php

namespace App\Http\Requests\Reservations;

use App\DataTables\ReservationDataTable;
use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the court and event space reservation lists (ReservationDataTable, EventReservationDataTable).
 */
class ReservationFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(ReservationDataTable::STATUS_FILTERS))],
            'source' => ['nullable', Rule::in(array_keys(CourtReservation::SOURCES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'court_id' => 'centro deportivo',
            'status' => 'estado',
            'source' => 'origen',
            'date_from' => 'desde',
            'date_to' => 'hasta',
        ];
    }
}
