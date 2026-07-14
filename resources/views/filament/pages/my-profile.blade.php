<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <label>Nome completo <input wire:model="name" class="w-full rounded-lg"></label>
        <label>Data de nascimento <input type="date" wire:model="dateOfBirth" class="w-full rounded-lg"></label>
        <label>E-mail <input type="email" wire:model="email" class="w-full rounded-lg"></label>
        <label>Telefone <input wire:model="phone" class="w-full rounded-lg"></label>
        <label>CPF / documento <input wire:model="documentNumber" class="w-full rounded-lg"></label>
    </div>
    <x-filament::button wire:click="save">Salvar perfil</x-filament::button>
</x-filament-panels::page>
