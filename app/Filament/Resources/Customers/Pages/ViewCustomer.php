<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\Actions\CreateAsaasCustomerAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAsaasCustomerAction::make(),
            EditAction::make(),
        ];
    }
}

