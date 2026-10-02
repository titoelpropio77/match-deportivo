<?php

namespace App\Http\Requests\StoreOrders;

use App\Http\Requests\Reservations\CancelReservationRequest;

/**
 * Cancellation of a sale by the store; `restock` puts the units of a paid sale back on the shelf
 * (unchecked when the customer kept the products).
 */
class CancelStoreOrderRequest extends CancelReservationRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['restock' => $this->boolean('restock')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'restock' => ['boolean'],
        ];
    }
}
