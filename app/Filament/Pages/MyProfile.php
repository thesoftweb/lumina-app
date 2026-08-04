<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class MyProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Meu perfil';

    protected static ?string $title = 'Meu perfil';

    protected string $view = 'filament.pages.my-profile';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher') && (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        $teacher = Auth::user()->teacher;

        $this->form->fill($teacher->only([
            'name',
            'date_of_birth',
            'email',
            'phone',
            'document_number',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados pessoais')
                    ->description('Mantenha seus dados cadastrais atualizados.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome completo')
                                    ->prefixIcon(Heroicon::OutlinedUser)
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('date_of_birth')
                                    ->label('Data de nascimento')
                                    ->prefixIcon(Heroicon::OutlinedCake)
                                    ->native(false),
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                                    ->email()
                                    ->maxLength(255)
                                    ->unique('teachers', 'email', ignorable: Auth::user()->teacher),
                                TextInput::make('phone')
                                    ->label('Telefone')
                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20),
                                TextInput::make('document_number')
                                    ->label('CPF / documento')
                                    ->prefixIcon(Heroicon::OutlinedIdentification)
                                    ->mask('999.999.999-99')
                                    ->maxLength(100)
                                    ->unique('teachers', 'document_number', ignorable: Auth::user()->teacher),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $teacher = Auth::user()->teacher;
        $teacher->update($this->form->getState());

        Notification::make()
            ->title('Perfil atualizado')
            ->body('Seus dados foram salvos com sucesso.')
            ->success()
            ->send();
    }
}
