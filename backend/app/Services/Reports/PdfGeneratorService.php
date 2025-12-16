<?php

namespace App\Services\Reports;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class PdfGeneratorService
{
    /**
     * Create mPDF instance with Arabic support
     */
    protected function createMpdf(string $orientation = 'P'): Mpdf
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'default_font_size' => 10,
            'default_font' => 'dejavusans',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 15,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,

            // Arabic/RTL Support
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,

            // Font directories
            'fontDir' => array_merge($fontDirs, [
                storage_path('fonts'),
            ]),

            // Font data with Arabic support
            'fontdata' => $fontData + [
                'dejavusans' => [
                    'R' => 'DejaVuSans.ttf',
                    'B' => 'DejaVuSans-Bold.ttf',
                    'I' => 'DejaVuSans-Oblique.ttf',
                    'BI' => 'DejaVuSans-BoldOblique.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],

            'tempDir' => storage_path('app/mpdf-temp'),
        ]);

        return $mpdf;
    }

    /**
     * Render view to HTML
     */
    protected function renderView(string $view, array $data): string
    {
        return View::make($view, $data)->render();
    }

    /**
     * Generate PDF and return as download response
     */
    public function download(string $view, array $data, string $filename): Response
    {
        $html = $this->renderView($view, $data);

        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Generate PDF and return as stream (view in browser)
     */
    public function stream(string $view, array $data, string $filename): Response
    {
        $html = $this->renderView($view, $data);

        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Generate PDF with landscape orientation
     */
    public function downloadLandscape(string $view, array $data, string $filename): Response
    {
        $html = $this->renderView($view, $data);

        $mpdf = $this->createMpdf('L');
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', 'S');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Save PDF to storage
     */
    public function save(string $view, array $data, string $path): string
    {
        $html = $this->renderView($view, $data);

        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($html);

        $fullPath = storage_path('app/' . $path);

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $mpdf->Output($fullPath, 'F');

        return $fullPath;
    }
}

