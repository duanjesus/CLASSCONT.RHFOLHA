/*
 * Entrada do AssetMapper para o painel administrativo (Twig).
 * O CSS (Tailwind) é carregado por <link> no base.html.twig e compilado pelo symfonycasts/tailwind-bundle.
 */

// Confirmação para formulários destrutivos: <form data-confirmar="Mensagem">
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirmar && !window.confirm(form.dataset.confirmar)) {
        event.preventDefault();
    }
});
