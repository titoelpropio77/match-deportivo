<?php

namespace App\Http\Requests\StoreOrders;

use App\Models\StoreOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the sales table (StoreOrderDataTable).
 */
class StoreOrderFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(StoreOrder::DISPLAY_STATUSES))],
            'source' => ['nullable', Rule::in(['app', 'admin'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
