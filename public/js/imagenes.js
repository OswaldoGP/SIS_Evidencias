document.addEventListener('DOMContentLoaded', function () {
    inicializarPreviewImagenes();
});

function inicializarPreviewImagenes() {
    const inputs = document.querySelectorAll('input[type="file"][name]');
    if (!inputs.length) return;

    const previewMap = {
        header: 'img/header-pdf-cb.png',
        footer: 'img/footer-pdf-cb.png',
        firma: 'img/firma-cb.png',
        sello: 'img/sello-cb.png',
    };

    inputs.forEach(input => {
        const name = input.getAttribute('name');
        if (!previewMap[name]) return;

        input.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const contenedor = input.closest('.pdf-card');
            const preview = contenedor?.querySelector('.previewPdf, .firmaPreview');
            if (!preview) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                preview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    });
}