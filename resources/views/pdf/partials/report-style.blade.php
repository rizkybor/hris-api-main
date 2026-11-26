{{-- Shared look of the company's reports (Project Progress, Staff Report):
     a plain corporate document -- one ink colour plus navy for headings and
     rules, hairline tables, numbered sections. No cards, pills or icons.
     Page numbers are drawn by App\Services\Pdf\ReportPageFooter. --}}
<style>
    @page { margin: 16mm 18mm 20mm 18mm; }
    body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2933; font-size: 9.5pt; line-height: 1.45; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    p { margin: 0; }

    /* Letterhead strip (page 1) */
    .masthead td { padding: 0; }
    .masthead .logo { width: 11mm; height: auto; }
    .masthead .company { font-size: 10pt; font-weight: bold; color: #0b1d51; letter-spacing: 0.4px; }
    .masthead .address { font-size: 7.5pt; color: #6b7280; margin-top: 0.6mm; }
    .masthead .doc-type { font-size: 7.5pt; color: #6b7280; letter-spacing: 1.2px; text-transform: uppercase; text-align: right; }
    .masthead .doc-date { font-size: 8.5pt; color: #1f2933; text-align: right; margin-top: 0.6mm; }
    .rule { border-top: 1.2pt solid #0b1d51; margin: 4mm 0 0 0; }
    .rule-thin { border-top: 0.4pt solid #0b1d51; margin: 0.7mm 0 7mm 0; }

    /* Title block */
    .doc-title { font-size: 18pt; font-weight: bold; color: #0b1d51; line-height: 1.15; }
    .doc-subtitle { font-size: 10pt; color: #4b5563; margin-top: 1.5mm; }
    .doc-meta { font-size: 8.5pt; color: #6b7280; margin-top: 2mm; }
    .doc-meta strong { color: #1f2933; font-weight: bold; }

    /* Numbered section headings */
    h2 { font-size: 10.5pt; font-weight: bold; color: #0b1d51; margin: 8mm 0 2.5mm 0; padding-bottom: 1.2mm; border-bottom: 0.6pt solid #cbd2d9; page-break-after: avoid; }
    h2 .n { color: #0b1d51; padding-right: 2.5mm; }

    /* Key facts: label / value pairs */
    .facts td { padding: 1.6mm 0; border-bottom: 0.4pt solid #e5e7eb; line-height: 1.35; }
    .facts .k { width: 38mm; color: #6b7280; }
    .facts .v { color: #1f2933; }

    /* Headline figures, separated by hairlines */
    .figures td { padding: 0 4mm; border-left: 0.6pt solid #cbd2d9; vertical-align: bottom; }
    .figures td:first-child { padding-left: 0; border-left: none; }
    .figure { font-size: 17pt; font-weight: bold; color: #0b1d51; line-height: 1.15; }
    .figure-label { font-size: 8pt; color: #6b7280; margin-top: 1mm; line-height: 1.3; }

    .bar { height: 2mm; background-color: #e5e7eb; margin-top: 3mm; }
    .bar div { height: 2mm; background-color: #0b1d51; }

    /* Data tables: header underline, hairline rows, no fills */
    .data th { font-size: 7.5pt; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; padding: 0 2mm 1.5mm 0; border-bottom: 0.8pt solid #1f2933; }
    .data td { padding: 1.8mm 2mm 1.8mm 0; border-bottom: 0.4pt solid #e5e7eb; line-height: 1.35; }
    .data tr { page-break-inside: avoid; }
    .data .group { page-break-after: avoid; }
    .data .group td { padding-top: 3.5mm; border-bottom: 0.4pt solid #cbd2d9; font-weight: bold; color: #0b1d51; font-size: 8.5pt; }
    .data .total td { border-top: 0.8pt solid #1f2933; border-bottom: none; font-weight: bold; }
    .num, .data .num { text-align: right; }
    .c, .data .c { text-align: center; }
    .data.attendance th, .data.attendance td { padding-left: 1mm; padding-right: 1mm; }
    .mono { font-family: "Courier", monospace; font-size: 8.5pt; }
    .muted { color: #6b7280; }
    .small { font-size: 8pt; }
    .warn { color: #b45309; }
    .bad { color: #b91c1c; }
    .ok { color: #15803d; }
    .note { font-size: 8pt; color: #6b7280; margin-top: 2mm; line-height: 1.45; }
</style>
