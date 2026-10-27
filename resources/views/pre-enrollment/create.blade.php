@extends('pre-enrollment.layout')

@section('title', 'Pré-matrícula '.$academicYear->year)

@php
    $selectClass = 'field appearance-auto';
@endphp

@section('content')
    <div class="text-center md:text-left">
        <p class="text-sm font-semibold uppercase tracking-wide text-azul-principal">Ficha de pré-matrícula</p>
        <h1 class="mt-1 text-2xl md:text-4xl font-bold text-gray-900">{{ $academicYear->description }}</h1>
        <p class="mt-3 text-gray-700">
            Preencha os dados abaixo. A secretaria entra em contato para confirmar a vaga e combinar os próximos passos.
            O envio não gera cobrança.
        </p>
        @if ($academicYear->admissions_ends_at)
            <p class="mt-2 text-sm text-gray-600">Inscrições até <strong>{{ $academicYear->admissions_ends_at->format('d/m/Y') }}</strong>.</p>
        @endif
        <p class="mt-2 text-sm text-gray-600">
            Já é aluno? A secretaria envia o link de rematrícula pelo WhatsApp, mas você também pode preencher esta ficha.
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

    <form method="POST" action="{{ route('pre-enrollment.store', ['year' => $academicYear->year]) }}" id="ficha" class="mt-6 space-y-6" novalidate>
        @csrf

        {{-- Honeypot: escondido de pessoas e leitores de tela --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Não preencha este campo</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" />
        </div>

        @foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $key)
            @if (old($key, $utm[$key] ?? null))
                <input type="hidden" name="{{ $key }}" value="{{ old($key, $utm[$key] ?? null) }}" />
            @endif
        @endforeach

        @if ($offers->isNotEmpty())
            @include('pre-enrollment.partials.offer-cards', ['offers' => $offers, 'academicYear' => $academicYear, 'selected' => null])
        @endif

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Dados da criança</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-pre-enrollment.input name="student_name" label="Nome completo da criança" required autocomplete="off" maxlength="255" />
                </div>
                <x-pre-enrollment.input name="student_birth_date" label="Data de nascimento" type="date" required
                    max="{{ today()->subDay()->toDateString() }}" />
                <div>
                    <label for="campo-student-gender" class="block text-sm font-semibold text-gray-800 mb-1">Sexo <span class="font-normal text-gray-500">(opcional)</span></label>
                    <select id="campo-student-gender" name="student_gender" class="{{ $selectClass }}"
                        @error('student_gender') aria-invalid="true" aria-describedby="campo-student-gender-erro" @enderror>
                        <option value="">Selecione</option>
                        @foreach (['F' => 'Feminino', 'M' => 'Masculino', 'O' => 'Outro'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('student_gender') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('student_gender')
                        <p id="campo-student-gender-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <x-pre-enrollment.input name="student_previous_school" label="Escola atual" maxlength="255"
                        hint="Deixe em branco se a criança ainda não estuda." />
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-start gap-3 min-h-[44px] cursor-pointer">
                        <input type="checkbox" name="student_has_special_needs" value="1" id="campo-student-has-special-needs"
                            class="mt-1 w-5 h-5 rounded border-gray-300 text-azul-principal" @checked(old('student_has_special_needs'))
                            aria-controls="bloco-necessidades" />
                        <span class="text-gray-800">A criança tem alguma necessidade específica (deficiência, TEA, TDAH, alergia, outra)</span>
                    </label>
                </div>
                <div id="bloco-necessidades" class="md:col-span-2 {{ old('student_has_special_needs') ? '' : 'hidden' }}">
                    <label for="campo-student-special-needs-notes" class="block text-sm font-semibold text-gray-800 mb-1">Conte um pouco para a escola se preparar <span class="font-normal text-gray-500">(opcional)</span></label>
                    <textarea id="campo-student-special-needs-notes" name="student_special_needs_notes" rows="3" maxlength="1000" class="field"
                        @error('student_special_needs_notes') aria-invalid="true" aria-describedby="campo-student-special-needs-notes-erro" @enderror>{{ old('student_special_needs_notes') }}</textarea>
                    @error('student_special_needs_notes')
                        <p id="campo-student-special-needs-notes-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Responsável</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-pre-enrollment.input name="guardian_name" label="Nome completo" required autocomplete="name" maxlength="255" />
                </div>
                <x-pre-enrollment.input name="guardian_document" label="CPF" required inputmode="numeric" autocomplete="off"
                    maxlength="14" placeholder="000.000.000-00" data-mask="cpf" />
                <div>
                    <label for="campo-guardian-kinship" class="block text-sm font-semibold text-gray-800 mb-1">Parentesco <span class="font-normal text-gray-500">(opcional)</span></label>
                    <select id="campo-guardian-kinship" name="guardian_kinship" class="{{ $selectClass }}"
                        @error('guardian_kinship') aria-invalid="true" aria-describedby="campo-guardian-kinship-erro" @enderror>
                        <option value="">Selecione</option>
                        @foreach ($kinships as $kinship)
                            <option value="{{ $kinship->value }}" @selected(old('guardian_kinship') === $kinship->value)>{{ \Illuminate\Support\Str::ucfirst($kinship->value) }}</option>
                        @endforeach
                    </select>
                    @error('guardian_kinship')
                        <p id="campo-guardian-kinship-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <x-pre-enrollment.input name="guardian_phone" label="Telefone / WhatsApp" type="tel" required inputmode="tel"
                    autocomplete="tel-national" maxlength="15" placeholder="(00) 00000-0000" data-mask="phone" />
                <x-pre-enrollment.input name="guardian_email" label="E-mail" type="email" inputmode="email" autocomplete="email" maxlength="255" />
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Endereço <span class="text-base font-normal text-gray-500">(opcional)</span></h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-pre-enrollment.input name="postal_code" label="CEP" inputmode="numeric" autocomplete="postal-code"
                    maxlength="9" placeholder="00000-000" data-mask="cep" hint="Preenchemos o endereço pelo CEP." />
                <x-pre-enrollment.input name="city_name" label="Cidade" autocomplete="address-level2" maxlength="255" />
                <div class="md:col-span-2">
                    <x-pre-enrollment.input name="address" label="Rua" autocomplete="address-line1" maxlength="255" />
                </div>
                <x-pre-enrollment.input name="address_number" label="Número" inputmode="numeric" maxlength="20" />
                <x-pre-enrollment.input name="neighborhood" label="Bairro" autocomplete="address-level3" maxlength="255" />
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-xl font-bold text-gray-900">Para finalizar</h2>
            <div class="mt-4 grid gap-4">
                <div>
                    <label for="campo-source" class="block text-sm font-semibold text-gray-800 mb-1">Como conheceu a escola? <span class="font-normal text-gray-500">(opcional)</span></label>
                    <select id="campo-source" name="source" class="{{ $selectClass }}"
                        @error('source') aria-invalid="true" aria-describedby="campo-source-erro" @enderror>
                        <option value="">Selecione</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" @selected(old('source') === $source->value)>{{ $source->getLabel() }}</option>
                        @endforeach
                    </select>
                    @error('source')
                        <p id="campo-source-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="campo-message" class="block text-sm font-semibold text-gray-800 mb-1">Dúvidas ou recado para a secretaria <span class="font-normal text-gray-500">(opcional)</span></label>
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
                            Autorizo a escola a usar os dados desta ficha para entrar em contato sobre a matrícula, conforme a
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
            <button type="submit" id="enviar"
                class="w-full min-h-[52px] rounded-xl bg-azul-principal hover:bg-azul-hover text-white text-lg font-bold px-6 transition disabled:opacity-60 disabled:cursor-wait">
                Enviar pré-matrícula
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('pre-enrollment.partials.form-script')
    <script>
        (function () {
            const needs = document.getElementById('campo-student-has-special-needs');
            const needsBlock = document.getElementById('bloco-necessidades');
            needs.addEventListener('change', () => needsBlock.classList.toggle('hidden', !needs.checked));
        })();
    </script>
@endpush
