{{-- Estilos comuns dos PDFs gerados com dompdf (boletim e histórico escolar). --}}
<style>
    @page {
        margin: 14mm 14mm 16mm;
    }

    body {
        margin: 0;
        color: #1e293b;
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8.5pt;
        line-height: 1.35;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-header {
        margin-bottom: 4mm;
        border-bottom: 1px solid #cbd5e1;
    }

    .pdf-header td {
        padding: 0 0 3mm;
        vertical-align: middle;
    }

    .pdf-logo {
        width: 20mm;
    }

    .pdf-logo img {
        max-width: 18mm;
        max-height: 18mm;
    }

    .pdf-school {
        color: #0f172a;
        font-size: 11pt;
        font-weight: bold;
    }

    .pdf-muted {
        color: #64748b;
        font-size: 7.5pt;
    }

    .pdf-title {
        margin: 2mm 0 4mm;
        color: #0f172a;
        font-size: 12.5pt;
        font-weight: bold;
        letter-spacing: 1.5px;
        text-align: center;
        text-transform: uppercase;
    }

    .pdf-subtitle {
        margin-top: 1mm;
        color: #64748b;
        font-size: 8pt;
        font-weight: normal;
        letter-spacing: 0;
        text-transform: none;
    }

    .pdf-section {
        margin: 4mm 0 1.5mm;
        color: #0369a1;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .pdf-info td {
        padding: 1.2mm 2mm;
        border: 1px solid #e2e8f0;
        vertical-align: top;
    }

    .pdf-label {
        display: block;
        color: #64748b;
        font-size: 6.5pt;
        text-transform: uppercase;
    }

    .pdf-grid th,
    .pdf-grid td {
        padding: 1.4mm 1.6mm;
        border: 1px solid #cbd5e1;
        text-align: center;
    }

    .pdf-grid th {
        background: #f1f5f9;
        color: #0f172a;
        font-size: 7.5pt;
        font-weight: bold;
    }

    .pdf-grid .left {
        text-align: left;
    }

    .pdf-grid tr.total td {
        background: #f8fafc;
        font-weight: bold;
    }

    .ok {
        color: #15803d;
        font-weight: bold;
    }

    .bad {
        color: #b91c1c;
        font-weight: bold;
    }

    .info {
        color: #0369a1;
        font-weight: bold;
    }

    .pdf-result {
        margin-top: 3mm;
        padding: 2.5mm 3mm;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
    }

    .pdf-notes {
        margin-top: 2mm;
        color: #475569;
        font-size: 7.5pt;
    }

    .pdf-signatures {
        margin-top: 16mm;
    }

    .pdf-signatures td {
        width: 50%;
        padding: 0 6mm;
        text-align: center;
        vertical-align: top;
    }

    .pdf-signatures .line {
        padding-top: 1.5mm;
        border-top: 1px solid #0f172a;
    }

    .pdf-verification {
        margin-top: 8mm;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .pdf-verification td {
        padding: 2mm 3mm;
        color: #64748b;
        font-size: 7pt;
        vertical-align: middle;
    }

    .pdf-verification img {
        width: 22mm;
        height: 22mm;
    }

    .pdf-watermark {
        position: fixed;
        top: 40%;
        left: 15%;
        color: #dc2626;
        font-size: 48pt;
        font-weight: bold;
        opacity: 0.2;
        transform: rotate(-28deg);
    }

    .pdf-footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        color: #94a3b8;
        font-size: 6.5pt;
        text-align: center;
    }
</style>
