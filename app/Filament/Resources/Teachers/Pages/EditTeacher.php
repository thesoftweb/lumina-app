<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = User::query()
            ->whereKey($data['user_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->when(Auth::user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->first();

        if (! $user || $user->teacher()->where('id', '!=', $this->record->id)->exists()) {
            throw ValidationException::withMessages([
                'data.user_id' => 'Selecione um usuário teacher disponível da sua empresa.',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
