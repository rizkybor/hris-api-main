<style>
    @page {
        margin: 0;
    }

    body {
        font-family: "Helvetica", "Arial", sans-serif;
        color: #1f2937;
        margin: 0;
        padding: 0;
        font-size: 11px;
    }

    .page {
        padding: 26mm 18mm 20mm 26mm;
    }

    .side-stripe {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 10px;
        background-color: #0c51d9;
    }

    /* Built as a horizontal box the height of the page and rotated
       -90deg around its own center: translate(-50%, -50%) first
       re-centers the box on the stripe's midpoint (left: 5px is the
       stripe's horizontal center, top: 50% its vertical center),
       then the rotation swings its long axis vertical without
       needing to hand-compute rotated offsets. */
    .side-stripe-label {
        position: fixed;
        top: 50%;
        left: 5px;
        width: 297mm;
        transform: translate(-50%, -50%) rotate(-90deg);
        transform-origin: center;
        text-align: center;
        color: #ffffff;
        font-size: 7.5px;
        font-weight: bold;
        letter-spacing: 2px;
        white-space: nowrap;
    }

    .watermark {
        position: fixed;
        bottom: 40mm;
        right: -10mm;
        width: 140mm;
        height: 119mm;
        opacity: 0.08;
    }

    .footer {
        position: fixed;
        bottom: 10mm;
        left: 26mm;
        right: 18mm;
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        color: #0c51d9;
    }

    .letterhead-center {
        text-align: center;
        margin-bottom: 6mm;
    }

    .letterhead-right {
        text-align: right;
        margin-bottom: 10mm;
    }

    .company-name {
        font-size: 20px;
        font-weight: bold;
        color: #0c51d9;
        margin: 0;
    }

    .company-address {
        font-size: 10px;
        color: #374151;
        margin: 2px 0 0 0;
    }

    .cancelled-stamp {
        position: fixed;
        top: 120mm;
        left: 40mm;
        width: 130mm;
        text-align: center;
        font-size: 42px;
        font-weight: bold;
        color: #dc2626;
        opacity: 0.35;
        transform: rotate(-25deg);
        border: 6px solid #dc2626;
        padding: 6px 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .doc-table th {
        background: #091842;
        color: #ffffff;
        text-align: left;
        padding: 6px 8px;
        font-size: 10.5px;
    }

    .doc-table td {
        border: 1px solid #dcdedd;
        padding: 6px 8px;
        vertical-align: top;
        font-size: 10.5px;
    }

    .section-title {
        font-weight: bold;
        font-size: 12px;
        margin: 6mm 0 3mm 0;
    }
</style>
