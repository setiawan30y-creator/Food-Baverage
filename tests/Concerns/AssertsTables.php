<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pemeriksa struktur tabel: jumlah & perataan kolom harus cocok dengan judul.
 * @2026-Alifiandra
 */
trait AssertsTables
{
    /** @return array{0:int,1:int} jumlah tabel & baris yang diperiksa */
    protected function assertTables(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $tables = 0;
        $rowsChecked = 0;

        foreach ($xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " table ")]') as $ti => $table) {
            $tables++;
            $head = $xp->query('./thead/tr', $table)->item(0);
            $rows = $xp->query('./tbody/tr | ./tbody/template/tr | ./tfoot/tr | ./tfoot/template/tr', $table);
            if (! $head) {
                // Tabel tanpa judul: semua baris harus sama lebar
                $widths = [];
                foreach ($rows as $tr) {
                    $widths[] = $this->width($xp, $tr);
                }
                $this->assertLessThanOrEqual(1, count(array_unique($widths)), "{$url} tabel #{$ti}: jumlah kolom tiap baris berbeda ".json_encode($widths));
                $rowsChecked += count($widths);

                continue;
            }

            $headCells = $this->cells($xp, $head);
            $cols = $this->width($xp, $head);
            $headLeft = [];
            $pos = 0;
            foreach ($headCells as $th) {
                $headLeft[$pos] = $this->isLeft($th, $pos);
                $pos += $this->span($th);
            }
            $label = fn ($i) => trim($headCells[$i]->textContent ?? '') ?: '(kolom '.($i + 1).')';

            foreach ($rows as $tr) {
                $w = $this->width($xp, $tr);
                $rowCells = $this->cells($xp, $tr);
                if ($tr->parentNode->nodeName === 'tbody' && count($rowCells) > 1) {
                    foreach ($rowCells as $c) {
                        $this->assertSame(1, $this->span($c), "{$url} tabel #{$ti}: baris data memakai sel gabungan (colspan) sehingga isinya tidak sejajar dengan judul kolom");
                    }
                }
                $text = mb_substr(preg_replace('/\s+/', ' ', trim($tr->textContent)), 0, 80);
                $this->assertSame($cols, $w, "{$url} tabel #{$ti}: baris punya {$w} kolom, judul punya {$cols} — \"{$text}\"");
                $pos = 0;
                foreach ($this->cells($xp, $tr) as $td) {
                    if ($this->span($td) === 1 && isset($headLeft[$pos])) {
                        $this->assertSame($headLeft[$pos], $this->isLeft($td, $pos),
                            "{$url} tabel #{$ti}: perataan isi kolom ".($pos + 1)." tidak sama dengan judulnya — \"{$text}\"");
                    }
                    $pos += $this->span($td);
                }
                $rowsChecked++;
            }
        }

        return [$tables, $rowsChecked];
    }

    /** @return DOMElement[] */
    protected function cells(DOMXPath $xp, DOMElement $tr): array
    {
        return iterator_to_array($xp->query('./td | ./th', $tr));
    }

    protected function span(DOMElement $cell): int
    {
        return max(1, (int) ($cell->getAttribute('colspan') ?: 1));
    }

    protected function width(DOMXPath $xp, DOMElement $tr): int
    {
        return array_sum(array_map(fn ($c) => $this->span($c), $this->cells($xp, $tr)));
    }

    /** Aturan CSS pusat: kolom pertama & kelas "l" rata kiri, selain itu rata tengah. */
    protected function isLeft(DOMElement $cell, int $pos): bool
    {
        return $pos === 0 || in_array('l', preg_split('/\s+/', $cell->getAttribute('class')), true);
    }
}
