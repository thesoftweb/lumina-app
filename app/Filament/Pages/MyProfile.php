<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MyProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Meu perfil';

    protected static ?string $title = 'Meu perfil';

    protected string $view = 'filament.pages.my-profile';

    public string $name = '';

    public ?string $dateOfBirth = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $documentNumber = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher') && (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        $teacher = Auth::user()->teacher;
        $this->name = $teacher->name;
        $this->dateOfBirth = $teacher->date_of_birth;
        $this->email = $teacher->email;
        $this->phone = $teacher->phone;
        $this->documentNumber = $teacher->document_number;
    }

    public function save(): void
    {
        $teacher = Auth::user()->teacher;
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'documentNumber' => ['nullable', 'string', 'max:100', Rule::unique('teachers', 'document_number')->ignore($teacher->id)],
        ]);

        $teacher->update([
            'name' => $this->name,
            'date_of_birth' => $this->dateOfBirth,
            'email' => $this->email,
            'phone' => $this->phone,
            'document_number' => $this->documentNumber,
        ]);

        Notification::make()->title('Perfil atualizado')->success()->send();
    }
}
