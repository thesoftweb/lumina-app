<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Exceptions\HttpResponseException;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected static ?string $title = 'Empresa';

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function mount(int|string $record = ''): void
    {
        abort_unless(CompanyResource::canViewAny(), 403);

        if (! Company::exists()) {
            throw new HttpResponseException(
                new \Illuminate\Http\RedirectResponse(CompanyResource::getUrl('create')),
            );
        }

        $companyId = auth()->user()->company_id;
        $company = $companyId
            ? Company::findOrFail($companyId)
            : (Company::count() === 1 ? Company::firstOrFail() : abort(403, 'Vincule seu usuário à empresa para acessar as configurações.'));

        abort_if($record !== '' && (string) $company->getKey() !== (string) $record, 403);
        parent::mount($company->getKey());
    }

    public function getSubheading(): ?string
    {
        return 'Gerencie os dados da instituição e suas preferências de avaliação.';
    }

    public function getBreadcrumbs(): array
    {
        return ['Configurações', 'Empresa'];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['asaas_key'] = null;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->label('Salvar alterações')];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Dados da empresa atualizados';
    }
}
