document.addEventListener('DOMContentLoaded', function () {
    inicializarTooltips();
});

document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('enviarWhatsapp')) return;

    const url = e.target.value;
    if (url) {
        window.open(url, '_blank');
        e.target.selectedIndex = 0;
    }
});

function inicializarTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

    const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );

    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}