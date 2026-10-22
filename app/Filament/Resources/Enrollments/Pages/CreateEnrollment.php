<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Enrollments\Schemas\EnrollmentWizardFields;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;

class CreateEnrollment extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard {
        getWizardComponent as protected getBaseWizardComponent;
    }

    protected static string $resource = EnrollmentResource::class;

    public function getWizardComponent(): Component
    {
        return $this->getBaseWizardComponent()
            ->extraAttributes(['class' => 'enrollment-create-wizard']);
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Aluno e período')->description('Selecione o aluno e o ano letivo')->schema(EnrollmentWizardFields::studentFields())->columns(2),
            Step::make('Matrícula')->description('Defina turma, data e situação')->schema(EnrollmentWizardFields::classroomFields())->columns(2),
            Step::make('Pagamento')->description('Escolha o plano e o vencimento')->schema(EnrollmentWizardFields::paymentFields())->columns(2),
            Step::make('Documentos')->description('Registre os documentos entregues')->schema(EnrollmentWizardFields::documentFields())->columns(3),
        ];
    }
}
