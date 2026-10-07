/*
 * Entrada do AssetMapper para o painel administrativo (Twig).
 * O CSS (Tailwind) é carregado por <link> no base.html.twig e compilado pelo symfonycasts/tailwind-bundle.
 */

// Confirmação para formulários destrutivos: <form data-confirmar="Mensagem">
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    // Clique duplo: o primeiro envio já está a caminho. Sem esta trava o segundo também saía, o
    // servidor o recusava ("competência já fechada") e a tela mostrava erro por uma ação que deu certo.
    if (form.dataset.enviando) {
        event.preventDefault();
        return;
    }
    if (form.dataset.confirmar && !window.confirm(form.dataset.confirmar)) {
        event.preventDefault();
        return;
    }
    if (form.method === 'post') {
        form.dataset.enviando = '1';
    }
});

// Voltar no navegador devolve a página guardada, com a trava ainda ligada: solta de novo
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-enviando]').forEach((form) => delete form.dataset.enviando);
});
