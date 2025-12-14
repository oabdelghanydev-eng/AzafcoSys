<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfGeneratorService
{
    /**
     * Generate PDF and return as download response
     */
    public function download(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data);

        // Configure PDF settings
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf->download($filename . '.pdf');
    }

    /**
     * Generate PDF and return as stream (view in browser)
     */
    public function stream(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf->stream($filename . '.pdf');
    }

    /**
     * Generate PDF with landscape orientation
     */
    public function downloadLandscape(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf->download($filename . '.pdf');
    }

    /**
     * Save PDF to storage
     */
    public function save(string $view, array $data, string $path): string
    {
        $pdf = Pdf::loadView($view, $data);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $pdf->save(storage_path('app/' . $path));

        return storage_path('app/' . $path);
    }
}
