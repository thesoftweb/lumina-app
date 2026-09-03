<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = User::query()
            ->whereKey($data['user_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->when(Auth::user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->first();

        if (! $user || $user->teacher()->exists()) {
            throw ValidationException::withMessages([
                'data.user_id' => 'Selecione um usuário teacher disponível da sua empresa.',
            ]);
        }

        return $data;
    }
}
