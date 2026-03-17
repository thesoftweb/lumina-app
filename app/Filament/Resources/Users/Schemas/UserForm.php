<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Usuário')
                    ->description('Preencha os dados do usuário.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->prefixIcon('heroicon-o-user')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->prefixIcon('heroicon-o-envelope')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignorable: fn($record) => $record)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->prefixIcon('heroicon-o-lock-closed')
                            ->label('Senha')
                            ->required(fn($record) => $record === null)
                            ->minLength(8)
                            ->confirmed()
                            ->dehydrateStateUsing(fn($state) => $state ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => $state !== null),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->prefixIcon('heroicon-o-lock-closed')
                            ->label('Confirmar Senha')
                            ->required(fn($record) => $record === null)
                            ->dehydrated(false),
                    ]),
                Section::make('Permissões')
                    ->description('Atribua roles e permissões ao usuário.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->options(fn() => Role::pluck('name', 'id'))
                            ->columns(1),
                    ]),
            ]);
    }
}
