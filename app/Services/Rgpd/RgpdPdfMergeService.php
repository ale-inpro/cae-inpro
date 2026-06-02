<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

use setasign\Fpdi\Fpdi;

final class RgpdPdfMergeService
{
    /**
     * @param list<string> $absolutePdfPaths
     */
    public static function mergeFiles(array $absolutePdfPaths): string
    {
        $pdf = new Fpdi();

        foreach ($absolutePdfPaths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $pageCount = $pdf->setSourceFile($path);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tpl = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tpl);

                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
        }

        return $pdf->Output('S');
    }
}