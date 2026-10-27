{{-- Comportamento comum das fichas: foco no resumo de erros, envio único, máscaras e endereço pelo CEP. --}}
<script>
    (function () {
        const form = document.getElementById('ficha');
        const button = document.getElementById('enviar');
        const summary = document.getElementById('resumo-erros');
        const sendingLabel = button.dataset.sending || 'Enviando…';

        if (summary) summary.focus();

        // Evita envio duplicado; a validação do servidor continua valendo
        form.addEventListener('submit', () => {
            button.disabled = true;
            button.textContent = sendingLabel;
        });

        const masks = {
            cpf: (d) => d.slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
            phone: (d) => d.slice(0, 11).replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d)(\d{4})$/, '$1-$2'),
            cep: (d) => d.slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2'),
        };

        document.querySelectorAll('[data-mask]').forEach((input) => {
            const apply = () => { input.value = masks[input.dataset.mask](input.value.replace(/\D/g, '')); };
            input.addEventListener('input', apply);
            if (input.value) apply();
        });

        // Endereço pelo CEP (ViaCEP); só preenche campos vazios
        const cep = document.getElementById('campo-postal-code');
        if (!cep) return;
        cep.addEventListener('blur', async () => {
            const digits = cep.value.replace(/\D/g, '');
            if (digits.length !== 8) return;
            try {
                const response = await fetch('https://viacep.com.br/ws/' + digits + '/json/');
                const data = await response.json();
                if (data.erro) return;
                const fill = (id, value) => {
                    const field = document.getElementById(id);
                    if (field && !field.value && value) field.value = value;
                };
                fill('campo-address', data.logradouro);
                fill('campo-neighborhood', data.bairro);
                fill('campo-city-name', data.localidade ? data.localidade + (data.uf ? '/' + data.uf : '') : '');
            } catch (e) {
                // Sem internet ou serviço fora do ar: o responsável digita o endereço
            }
        });
    })();
</script>
