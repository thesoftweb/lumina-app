<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Matrícula')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Aluno e período')
                            ->icon('heroicon-o-user')
                            ->schema(EnrollmentFormFields::studentFields())
                            ->columns(2),
                        Tab::make('Matrícula')
                            ->icon('heroicon-o-academic-cap')
                            ->schema(EnrollmentFormFields::classroomFields())
                            ->columns(2),
                        Tab::make('Pagamento')
                            ->icon('heroicon-o-banknotes')
                            ->schema(EnrollmentFormFields::paymentFields())
                            ->columns(2),
                        Tab::make('Documentos')
                            ->icon('heroicon-o-document-check')
                            ->schema(EnrollmentFormFields::documentFields())
                            ->columns(3),
                    ]),
            ]);
    }
}
