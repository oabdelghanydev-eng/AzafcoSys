<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>@yield('title', 'Report')</title>
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            direction: rtl;
            text-align: right;
        }

        /* RTL Support for Arabic */
        .rtl-text {
            direction: rtl;
            unicode-bidi: bidi-override;
            text-align: right;
        }

        .ltr-text {
            direction: ltr;
            unicode-bidi: embed;
            text-align: left;
        }

        /* Page Setup */
        @page {
            margin: 20mm 15mm 25mm 15mm;
        }

        /* Header */
        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 14px;
            color: #666;
        }

        .header .date {
            font-size: 12px;
            margin-top: 5px;
        }

        /* Section */
        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-left: 4px solid #333;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 10px;
        }

        td {
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            background-color: #f9f9f9;
            font-weight: bold;
        }

        /* Summary Box */
        .summary-box {
            border: 2px solid #333;
            padding: 15px;
            margin-top: 20px;
        }

        .summary-box h3 {
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .summary-row {
            display: block;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            display: inline-block;
            width: 60%;
        }

        .summary-value {
            display: inline-block;
            width: 35%;
            text-align: right;
            font-weight: bold;
        }

        .final-total {
            font-size: 14px;
            background-color: #f0f0f0;
            padding: 10px;
            margin-top: 10px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
            padding: 10px;
            border-top: 1px solid #ddd;
        }

        /* Utility Classes */
        .positive {
            color: #28a745;
        }

        .negative {
            color: #dc3545;
        }

        .highlight {
            background-color: #fff3cd;
        }

        .page-break {
            page-break-after: always;
        }

        /* Number formatting */
        .money {
            font-family: DejaVu Sans Mono, monospace;
        }
    </style>
    @yield('styles')
</head>

<body>
    <div class="header">
        <h1>{{ config('app.name', 'Company Name') }}</h1>
        <div class="subtitle">@yield('report-title')</div>
        <div class="date">@yield('report-date')</div>
    </div>

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        Generated: {{ now()->format('d/m/Y H:i') }} | Page <span class="page-num"></span>
    </div>
</body>

</html>