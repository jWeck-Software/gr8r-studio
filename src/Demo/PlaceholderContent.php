<?php

namespace App\Demo;

/**
 * Content for the demo Files, which come without real uploads: small, always the same, and
 * readable by what the File's type says it is (a picture, a one-page PDF, an archive, JSON),
 * or a short note for the rest.
 */
final class PlaceholderContent
{
    public static function for(string $name, string $type): string
    {
        $note = \sprintf('%s is a demo File of Gr8r Studio. Upload a file of your own to share real work.', $name);

        return match (true) {
            'img' === $type => self::png(160, 100),
            'pdf' === $type => self::pdf($note),
            'zip' === $type => "PK\x05\x06".str_repeat("\0", 18), // an empty archive
            str_ends_with($name, '.json') => json_encode(['note' => $note], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n",
            default => $note."\n",
        };
    }

    /** A soft diagonal gradient, as a PNG. */
    private static function png(int $width, int $height): string
    {
        $rows = '';
        for ($y = 0; $y < $height; ++$y) {
            $rows .= "\0"; // no filter
            for ($x = 0; $x < $width; ++$x) {
                $t = ($x / $width + $y / $height) / 2;
                $rows .= \chr((int) (224 - 90 * $t)).\chr((int) (214 - 60 * $t)).\chr((int) (250 - 30 * $t));
            }
        }
        $chunk = static fn (string $kind, string $data): string => pack('N', \strlen($data)).$kind.$data.pack('N', crc32($kind.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress($rows, 9))
            .$chunk('IEND', '');
    }

    /** A one-page PDF that says `$text`. */
    private static function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
        $stream = "BT /F1 11 Tf 56 760 Td ($text) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.\strlen($stream)." >>\nstream\n$stream\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$object\nendobj\n";
        }
        $xref = \strlen($pdf);
        $pdf .= 'xref
0 '.(\count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(\count($objects) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
