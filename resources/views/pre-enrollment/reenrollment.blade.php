@extends('pre-enrollment.layout')

@section('title', 'Rematrícula '.$academicYear->year)

@php
    $guardianFirstName = \Illuminate\Support\Str::before(\Illuminate\Support\Str::squish($preEnrollment->guardian_name), ' ');
    $currentClassroom = $preEnrollment->previousEnrollment?->classroom?->name;
@endphp

@section('content')
    <div class="text-center md:text-left">
        <p class="text-sm font-semibold uppercase tracking-wide text-azul-principal">Rematrícula {{ $academicYear->year }}</p>
        <h1 class="mt-1 text-2xl md:text-4xl font-bold text-gray-900">Olá, {{ $guardianFirstName }}!</h1>
        <p class="mt-3 text-gray-700">
            Confirme a rematrícula para {{ $academicYear->description }}, atualize seus dados de contato e escolha a série.
            A secretaria confere tudo e entra em contato.
        </p>
    </div>

    @if ($academicYear->admissions_notes)
        <aside class="mt-6 rounded-xl border border-verde-principal/40 bg-verde-claro p-4 text-gray-800" aria-label="Informações da escola">
            <p class="font-semibold text-verde-secundaria">Importante</p>
            <p class="mt-1 text-sm leading-relaxed">{!! nl2br(e($academicYear->admissions_notes)) !!}</p>
        </aside>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-xl border border-red-300 bg-red-50 p-4" role="alert" tabindex="-1" id="resumo-erros">
            <p class="font-semibold text-red-800">Confira {{ $errors->count() === 1 ? 'o campo abaixo' : 'os '.$errors->count().' campos abaixo' }}:</p>
            <ul class="mt-2 list-disc list-inside text-sm text-red-800 space-y-1">
                @foreach ($errors->keys() as $field)
                    <li><a href="#campo-{{ str_replace('_', '-', $field) }}" class="underline">{{ $errors->first($field) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pre-enrollment.reenrollment.store', ['year' => $academicYear->year, 'token' => $preEnrollment->access_token]) }}"
        id="ficha" class="mt-6 space-y-6" novalidate>
        @csrf

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8" aria-labelledby="titulo-aluno">
            <h2 id="titulo-aluno" class="text-xl font-bold text-gray-900">Aluno</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-gray-600">Nome</dt>
                    <dd class="mt-1 text-lg text-gray-900">{{ $preEnrollment->student_name }}</dd>
                </div>
                @if ($currentClassroom)
                    <div>
                        <dt class="text-sm font-semibold text-gray-600">Turma atual</dt>
                        <dd class="mt-1 text-lg text-gray-900">{{ $currentClassroom }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-semibold text-gray-600">Responsável</dt>
                    <dd class="mt-1 text-gray-900">{{ $preEnrollment->guardian_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-gray-600">CPF do responsável</dt>
                    <dd class="mt-1 text-gray-900 font-mono">{{ $maskedDocument }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-sm text-gray-600">Algum dado do aluno está errado? Avise no recado abaixo que a secretaria corrige.</p>
        </section>

        @if ($offers->isNotEmpty())
            @include('pre-enrollment.partials.offer-cards', ['offers' => $offers, 'academicYear' => $academicYear, 'selected' => $preEnrollment->admission_offer_id])
        @endif

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Contato</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-pre-enrollment.input name="guardian_phone" label="Telefone / WhatsApp" type="tel" required inputmode="tel"
                    autocomplete="tel-national" maxlength="15" placeholder="(00) 00000-0000" data-mask="phone"
                    :value="$preEnrollment->guardian_phone" />
                <x-pre-enrollment.input name="guardian_email" label="E-mail" type="email" inputmode="email" autocomplete="email" maxlength="255"
                    :value="$preEnrollment->guardian_email" />
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Endereço <span class="text-base font-normal text-gray-500">(opcional)</span></h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-pre-enrollment.input name="postal_code" label="CEP" inputmode="numeric" autocomplete="postal-code"
                    maxlength="9" placeholder="00000-000" data-mask="cep" hint="Preenchemos o endereço pelo CEP."
                    :value="$preEnrollment->postal_code" />
                <x-pre-enrollment.input name="city_name" label="Cidade" autocomplete="address-level2" maxlength="255"
                    :value="$preEnrollment->city_name" />
                <div class="md:col-span-2">
                    <x-pre-enrollment.input name="address" label="Rua" autocomplete="address-line1" maxlength="255"
                        :value="$preEnrollment->address" />
                </div>
                <x-pre-enrollment.input name="address_number" label="Número" inputmode="numeric" maxlength="20"
                    :value="$preEnrollment->address_number" />
                <x-pre-enrollment.input name="neighborhood" label="Bairro" autocomplete="address-level3" maxlength="255"
                    :value="$preEnrollment->neighborhood" />
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Para finalizar</h2>
            <div class="mt-4 grid gap-4">
                <div>
                    <label for="campo-message" class="block text-sm font-semibold text-gray-800 mb-1">Recado para a secretaria <span class="font-normal text-gray-500">(opcional)</span></label>
                    <textarea id="campo-message" name="message" rows="4" maxlength="2000" class="field"
                        @error('message') aria-invalid="true" aria-describedby="campo-message-erro" @enderror>{{ old('message') }}</textarea>
                    @error('message')
                        <p id="campo-message-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="consent" value="1" id="campo-consent" required aria-required="true"
                            class="mt-1 w-5 h-5 rounded border-gray-300 text-azul-principal" @checked(old('consent'))
                            @error('consent') aria-invalid="true" aria-describedby="campo-consent-erro" @enderror />
                        <span class="text-sm text-gray-800">
                            Autorizo a escola a usar os dados desta ficha para entrar em contato sobre a rematrícula, conforme a
                            Lei Geral de Proteção de Dados (LGPD). <span class="text-red-600" aria-hidden="true">*</span>
                        </span>
                    </label>
                    @error('consent')
                        <p id="campo-consent-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="sticky bottom-0 -mx-4 px-4 py-3 bg-gray-50/95 backdrop-blur border-t border-gray-200 md:static md:mx-0 md:px-0 md:bg-transparent md:border-0">
            <button type="submit" id="enviar" data-sending="Confirmando…"
                class="w-full min-h-[52px] rounded-xl bg-azul-principal hover:bg-azul-hover text-white text-lg font-bold px-6 transition disabled:opacity-60 disabled:cursor-wait">
                Confirmar rematrícula
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('pre-enrollment.partials.form-script')
@endpush
