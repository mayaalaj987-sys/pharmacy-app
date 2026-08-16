<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected function validPdfUpload(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->validPdfContent());
    }

    protected function validPdfContent(): string
    {
        $headerAndObject = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $xrefOffset = strlen($headerAndObject);
        $pdf = $headerAndObject
            ."xref\n0 2\n0000000000 65535 f \n0000000009 00000 n \n"
            ."trailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
