<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function getSubheading(): ?string
    {
        return 'Atualize o cadastro do responsável e acompanhe suas faturas.';
    }

    public function hasFormWrapper(): bool
    {
        return false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Salvar alterações');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
