<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function getSubheading(): ?string
    {
        return 'Responsáveis financeiros e seus vínculos. Clique em uma linha para editar o cadastro.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo cliente'),
        ];
    }
}
