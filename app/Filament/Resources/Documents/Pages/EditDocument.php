<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['document_template_id'])) {
            $template = DocumentTemplate::find($data['document_template_id']);

            if ($template) {
                $templateData = [
                    'customer_name' => $data['customer_name'] ?? '',
                    'customer_email' => $data['customer_email'] ?? '',
                    'amount' => $data['amount'] ?? '',
                    'date' => $data['date'] ?? '',
                    'receipt_number' => $data['receipt_number'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'cpf' => $data['cpf'] ?? '',
                    'cnpj' => $data['cnpj'] ?? '',
                    'company_name' => $data['company_name'] ?? '',
                    'company_address' => $data['company_address'] ?? '',
                    'document_number' => $data['document_number'] ?? '',
                ];

                $data['content'] = DocumentService::previewDocument($template->content, $templateData);
                $data['data'] = json_encode($templateData);
            }
        }

        return $data;
    }
}
