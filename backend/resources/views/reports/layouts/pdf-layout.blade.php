<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>@yield('title', 'تقرير / Report')</title>
    <style>
        /* ═══════════════════════════════════════════════════════════════════
           Professional Arabic/English PDF Styles
           Optimized for mPDF with proper RTL support
           ═══════════════════════════════════════════════════════════════════ */

        /* Body - RTL Primary */
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #2d3748;
            direction: rtl;
            text-align: right;
        }

        /* ═══════════════════════════════════════════════════════════════════
           HEADER SECTION
           ═══════════════════════════════════════════════════════════════════ */
        .header {
            text-align: center;
            padding: 15px 20px;
            margin-bottom: 20px;
            background-color: #1a365d;
            color: #fff;
            border-radius: 0 0 8px 8px;
        }

        .header .company-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .header .report-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #e2e8f0;
        }

        .header .report-date {
            font-size: 11pt;
            color: #cbd5e0;
            background-color: rgba(255, 255, 255, 0.15);
            display: inline-block;
            padding: 4px 15px;
            border-radius: 15px;
            margin-top: 5px;
        }

        /* ═══════════════════════════════════════════════════════════════════
           BILINGUAL LABELS
           ═══════════════════════════════════════════════════════════════════ */
        .ar {
            font-weight: bold;
        }

        .en {
            color: #718096;
            font-size: 0.85em;
        }

        /* ═══════════════════════════════════════════════════════════════════
           SECTION STYLES
           ═══════════════════════════════════════════════════════════════════ */
        .section {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background-color: #edf2f7;
            padding: 10px 12px;
            margin-bottom: 12px;
            border-right: 4px solid #3182ce;
            color: #2d3748;
        }

        .section-title .number {
            display: inline-block;
            background-color: #3182ce;
            color: #fff;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 20px;
            border-radius: 50%;
            margin-left: 8px;
            font-size: 10pt;
        }

        .section-title .en {
            color: #718096;
            font-weight: normal;
            font-size: 0.9em;
        }

        /* ═══════════════════════════════════════════════════════════════════
           TABLE STYLES
           ═══════════════════════════════════════════════════════════════════ */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        th,
        td {
            border: 1px solid #e2e8f0;
            padding: 7px 10px;
            text-align: right;
            vertical-align: middle;
        }

        th {
            background-color: #f7fafc;
            font-weight: bold;
            font-size: 9pt;
            color: #4a5568;
        }

        th .ar {
            display: block;
            font-size: 9pt;
            color: #2d3748;
        }

        th .en {
            display: block;
            font-size: 7pt;
            color: #a0aec0;
            font-weight: normal;
        }

        td {
            font-size: 9pt;
            color: #2d3748;
        }

        .total-row {
            background-color: #ebf8ff;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid #3182ce;
            color: #1a365d;
        }

        /* Text alignment */
        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        .text-center {
            text-align: center !important;
        }

        /* ═══════════════════════════════════════════════════════════════════
           MONEY / CURRENCY STYLES
           ═══════════════════════════════════════════════════════════════════ */
        .money {
            font-weight: 600;
            white-space: nowrap;
        }

        .positive {
            color: #38a169;
        }

        .negative {
            color: #e53e3e;
        }

        .highlight {
            background-color: #fefcbf !important;
        }

        /* ═══════════════════════════════════════════════════════════════════
           SUMMARY BOX
           ═══════════════════════════════════════════════════════════════════ */
        .summary-box {
            border: 2px solid #3182ce;
            border-radius: 8px;
            padding: 15px 18px;
            margin-top: 20px;
            background-color: #f0fff4;
            page-break-inside: avoid;
        }

        .summary-box h3 {
            font-size: 12pt;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3182ce;
            color: #1a365d;
        }

        .summary-row {
            padding: 8px 0;
            border-bottom: 1px dotted #cbd5e0;
            overflow: hidden;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            display: inline-block;
            width: 55%;
            color: #4a5568;
        }

        .summary-value {
            display: inline-block;
            width: 40%;
            text-align: left;
            font-weight: bold;
            font-size: 11pt;
        }

        .final-total {
            font-size: 12pt;
            background-color: #1a365d;
            color: #fff;
            padding: 12px 15px;
            margin-top: 12px;
            border-radius: 5px;
        }

        .final-total .summary-label {
            color: #e2e8f0;
        }

        .final-total .summary-value {
            color: #48bb78;
            font-size: 14pt;
        }

        /* ═══════════════════════════════════════════════════════════════════
           INFO BOX
           ═══════════════════════════════════════════════════════════════════ */
        .info-box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .info-row {
            display: inline-block;
            width: 48%;
            margin-bottom: 8px;
            vertical-align: top;
        }

        .info-label {
            color: #718096;
            font-size: 8pt;
        }

        .info-value {
            font-weight: bold;
            color: #2d3748;
            font-size: 10pt;
        }

        /* ═══════════════════════════════════════════════════════════════════
           FOOTER
           ═══════════════════════════════════════════════════════════════════ */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #a0aec0;
            padding: 8px 15px;
            border-top: 1px solid #e2e8f0;
            background-color: #f7fafc;
        }

        /* ═══════════════════════════════════════════════════════════════════
           UTILITY CLASSES
           ═══════════════════════════════════════════════════════════════════ */
        .no-data {
            text-align: center;
            padding: 20px;
            color: #a0aec0;
            font-style: italic;
            background-color: #f7fafc;
            border-radius: 5px;
        }

        .page-break {
            page-break-after: always;
        }

        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 12px 0;
        }

        /* Badge styles */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
        }

        .badge-success {
            background-color: #c6f6d5;
            color: #276749;
        }

        .badge-warning {
            background-color: #fefcbf;
            color: #975a16;
        }

        .badge-danger {
            background-color: #fed7d7;
            color: #c53030;
        }

        .badge-info {
            background-color: #bee3f8;
            color: #2b6cb0;
        }
    </style>
    @yield('styles')
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <div class="company-name"> الكواتيحي/ ElKawatihy </div>
        <div class="report-title">@yield('report-title')</div>
        <div class="report-date">@yield('report-date')</div>
    </div>

    {{-- Main Content --}}
    <div class="content">
        @yield('content')
    </div>

    {{-- Footer --}}
    <div class="footer">
        <span class="ar">تم الإنشاء:</span> {{ now()->format('d/m/Y H:i') }}
    </div>
</body>

</html>