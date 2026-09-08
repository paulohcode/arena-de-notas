<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClassAccessPdfService
{
    public const INITIAL_PASSWORD = 'aluno123';

    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const ROWS_PER_PAGE = 36;

    /**
     * @param  Collection<int, User>  $students
     */
    public function render(SchoolClass $class, Collection $students, string $loginUrl): string
    {
        $rows = $students
            ->map(fn (User $student): array => [
                'name' => $student->name,
                'email' => $student->email,
                'password' => $student->must_change_password
                    ? self::INITIAL_PASSWORD
                    : 'Alterada pelo aluno',
            ])
            ->values()
            ->all();

        $chunks = array_chunk($rows, self::ROWS_PER_PAGE);
        if ($chunks === []) {
            $chunks = [[]];
        }

        $pageCount = count($chunks);
        $pages = [];

        foreach ($chunks as $index => $chunk) {
            $pages[] = $this->pageContent($class, $chunk, $loginUrl, $index + 1, $pageCount);
        }

        return $this->assemble($pages);
    }

    public function filename(SchoolClass $class): string
    {
        $slug = Str::slug($class->name);

        if ($slug === '') {
            $slug = 'turma-'.$class->id;
        }

        return 'acessos-'.$slug.'.pdf';
    }

    /**
     * @param  list<array{name: string, email: string, password: string}>  $rows
     */
    private function pageContent(
        SchoolClass $class,
        array $rows,
        string $loginUrl,
        int $pageNumber,
        int $pageCount,
    ): string {
        $ops = [];
        $area = $class->area?->name;
        $subtitleParts = array_values(array_filter([
            $class->name,
            $area,
            $class->year,
        ]));

        $ops[] = 'BT';
        $ops[] = '/F2 18 Tf';
        $ops[] = '40 792 Td';
        $ops[] = $this->pdfString('Acessos da turma').' Tj';
        $ops[] = '/F1 11 Tf';
        $ops[] = '0 -18 Td';
        $ops[] = $this->pdfString(implode(' · ', $subtitleParts)).' Tj';
        $ops[] = '0 -14 Td';
        $ops[] = $this->pdfString('Entrar em: '.$loginUrl).' Tj';
        $ops[] = '0 -14 Td';
        $ops[] = $this->pdfString('A senha inicial é aluno123 até o aluno trocar.').' Tj';
        $ops[] = 'ET';

        $headerY = 712;
        $ops[] = '0.7 w';
        $ops[] = '40 '.$headerY.' m 555 '.$headerY.' l S';
        $ops[] = 'BT';
        $ops[] = '/F2 10 Tf';
        $ops[] = '42 '.($headerY - 14).' Td';
        $ops[] = $this->pdfString('Nome').' Tj';
        $ops[] = '208 0 Td';
        $ops[] = $this->pdfString('E-mail de acesso').' Tj';
        $ops[] = '210 0 Td';
        $ops[] = $this->pdfString('Senha inicial').' Tj';
        $ops[] = 'ET';
        $ops[] = '40 '.($headerY - 20).' m 555 '.($headerY - 20).' l S';

        $y = $headerY - 36;

        if ($rows === []) {
            $ops[] = 'BT /F1 10 Tf 42 '.$y.' Td '.$this->pdfString('Nenhum aluno cadastrado nesta turma.').' Tj ET';
        } else {
            foreach ($rows as $row) {
                $ops[] = 'BT';
                $ops[] = '/F1 10 Tf';
                $ops[] = '42 '.$y.' Td';
                $ops[] = $this->pdfString($this->fit($row['name'], 36)).' Tj';
                $ops[] = '208 0 Td';
                $ops[] = $this->pdfString($this->fit($row['email'], 38)).' Tj';
                $ops[] = '210 0 Td';
                $ops[] = $this->pdfString($this->fit($row['password'], 22)).' Tj';
                $ops[] = 'ET';
                $y -= 16;
            }
        }

        $ops[] = 'BT /F1 8 Tf 40 36 Td '.$this->pdfString(
            'Página '.$pageNumber.' de '.$pageCount.' · documento para distribuir os acessos da turma.'
        ).' Tj ET';

        return implode("\n", $ops);
    }

    /**
     * @param  list<string>  $pageContents
     */
    private function assemble(array $pageContents): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageIds = [];

        foreach ($pageContents as $content) {
            $contentId = count($objects) + 1;
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";

            $pageIds[] = count($objects) + 1;
            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents %d 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId,
            );
        }

        $kids = implode(' ', array_map(fn (int $id): string => $id.' 0 R', $pageIds));
        $objects[1] = sprintf('<< /Type /Pages /Count %d /Kids [%s] >>', count($pageIds), $kids);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= 'xref\n0 '.count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= 'trailer << /Size '.count($offsets).' /Root 1 0 R >>\n';
        $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    private function fit(string $text, int $maxChars): string
    {
        $normalized = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;

        if (mb_strlen($normalized) <= $maxChars) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $maxChars - 3).'...';
    }

    private function pdfString(string $text): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted === false) {
            $converted = iconv('UTF-8', 'Windows-1252//IGNORE', $text) ?: $text;
        }

        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);

        return '('.$escaped.')';
    }
}
