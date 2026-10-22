<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\ProductionBatch;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\StockCount;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga semua tabel di semua halaman: setiap baris punya jumlah sel yang sama
 * dengan judul kolom, dan perataan judul kolom sama dengan perataan isinya
 * (tidak ada data yang tampil di bawah judul kolom yang salah).
 *
 * @2026-Alifiandra
 */
class TableLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function u(string $email): User
    {
        tenancy()->clear();

        return User::where('email', $email)->firstOrFail();
    }

    protected function first(string $class, array $where = [])
    {
        return $class::withoutGlobalScopes()->where($where)->orderBy('id')->firstOrFail();
    }

    /** Isi data di semua modul supaya setiap tabel punya baris untuk diperiksa. */
    protected function fillData(): void
    {
        $cashier = $this->u('kasir@contoh.test');
        $menu = $this->first(Menu::class, ['name' => 'Kentang Crispy Keju']);
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 100000]);
        $o1 = $this->actingAs($cashier)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 'a', 'discount' => 1000, 'discount_reason' => 'Promo',
            'items' => [['menu_id' => $menu->id, 'qty' => 2, 'note' => 'Pedas']]])->json();
        $this->actingAs($cashier)->postJson('/pos/orders', ['type' => 'takeaway', 'idempotency_key' => 'b', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]]);
        $this->actingAs($cashier)->postJson("/orders/{$o1['id']}/pay", ['method' => 'cash', 'received' => 50000]);
        $this->actingAs($cashier)->post('/cashier/movements', ['type' => 'expense', 'amount' => 3000, 'note' => 'Beli es batu']);

        $table = $this->first(DiningTable::class);
        tenancy()->clear();
        $this->postJson("/t/{$table->qr_token}", ['idempotency_key' => 'qr', 'items' => [['menu_id' => $menu->id, 'qty' => 1]]]);

        $owner = $this->u('owner@contoh.test');
        $keju = $this->first(InventoryItem::class, ['name' => 'Keju mozzarella']);
        $this->actingAs($owner)->post('/purchases', ['date' => today()->toDateString(), 'action' => 'receive', 'lines' => [['inventory_item_id' => $keju->id, 'qty' => 1, 'unit' => 'kilogram', 'price' => 120000]]]);
        $this->actingAs($this->u('gudang@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $keju->id, 'qty' => 900, 'reason' => 'basi', 'note' => 'Lupa masuk kulkas']);
        $this->actingAs($this->u('manajer@contoh.test'))->post('/inventory/waste', ['inventory_item_id' => $keju->id, 'qty' => 10, 'reason' => 'tumpah']);
        $this->actingAs($owner)->post('/inventory/counts');
        $this->actingAs($owner)->post('/inventory/adjust', ['inventory_item_id' => $keju->id, 'direction' => 'in', 'qty' => 5, 'reason' => 'Koreksi input']);

        $emp = $this->first(Employee::class, ['code' => 'K002']);
        $this->actingAs($owner)->post('/leaves', ['employee_id' => $emp->id, 'type' => 'lembur', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'hours' => 2, 'reason' => 'Pesanan ramai']);
        $this->actingAs($owner)->post('/attendance/manual', ['employee_id' => $emp->id, 'type' => 'in', 'occurred_at' => now()->format('Y-m-d\TH:i'), 'note' => 'GPS tidak tersedia']);
        $this->actingAs($owner)->post("/employees/{$emp->id}/advances", ['date' => today()->toDateString(), 'amount' => 200000, 'installment' => 50000]);
        $this->actingAs($owner)->post('/schedule', ['cells' => [$emp->id => [now()->startOfWeek()->toDateString() => 'P']]]);
        $this->actingAs($owner)->post('/payroll', ['period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()]);

        $this->actingAs($cashier)->post('/cashier/close', ['count_method' => 'denomination', 'denominations' => [100000 => 1, 20000 => 1, 10000 => 1], 'reason' => 'Uang kembalian kurang']);
        tenancy()->clear();
    }

    public function test_every_table_header_matches_its_cells(): void
    {
        $this->fillData();
        $owner = $this->u('owner@contoh.test');

        $id = fn ($class, $where = []) => $this->first($class, $where)->id;
        $from = today()->startOfMonth()->toDateString();
        $to = today()->toDateString();
        $urls = [
            '/', '/pos', '/orders', '/orders/'.$id(Order::class), '/tables', '/customers',
            '/menus', '/menus/'.$id(Menu::class).'/edit',
            '/recipes', '/recipes/create', '/recipes/create?type=sub_recipe', '/recipes/'.$id(Recipe::class, ['type' => 'menu']).'/edit',
            '/recipes/'.$id(Recipe::class, ['type' => 'sub_recipe']).'/edit',
            '/production', '/production/'.$id(ProductionBatch::class), '/production/create?recipe_id='.$id(Recipe::class, ['type' => 'sub_recipe']),
            '/inventory/stock', '/inventory/stock/'.$id(InventoryItem::class, ['name' => 'Keju mozzarella']), '/inventory/items',
            '/inventory/waste', '/inventory/counts', '/inventory/counts/'.$id(StockCount::class), '/inventory/opening',
            '/suppliers', '/purchases', '/purchases/create', '/purchases/'.$id(Purchase::class),
            '/cashier', '/closings', '/closings/'.$id(CashClosing::class),
            '/employees', '/schedule', '/attendance', '/leaves', '/payroll', '/payroll/'.$id(PayrollRun::class), '/my/payslips',
            "/reports/sales?from={$from}&to={$to}", "/reports/menu?from={$from}&to={$to}", "/reports/stock?from={$from}&to={$to}", "/reports/waste?from={$from}&to={$to}",
            '/audit', '/settings/users',
        ];

        $checkedTables = 0;
        $checkedRows = 0;
        foreach ($urls as $url) {
            $res = $this->actingAs($owner)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "GET {$url}");
            [$t, $r] = $this->assertTables((string) $res->getContent(), $url);
            $checkedTables += $t;
            $checkedRows += $r;
        }

        // Halaman kasir yang shift-nya masih buka (tabel kas masuk/keluar)
        $cashier = $this->u('kasir@contoh.test');
        $this->actingAs($cashier)->post('/cashier/open', ['opening_cash' => 50000]);
        $this->actingAs($cashier)->post('/cashier/movements', ['type' => 'income', 'amount' => 2000, 'note' => 'Titipan']);
        foreach (['/cashier', '/cashier/close'] as $url) {
            [$t, $r] = $this->assertTables((string) $this->actingAs($cashier)->get($url)->getContent(), $url);
            $checkedTables += $t;
            $checkedRows += $r;
        }

        $this->assertGreaterThan(40, $checkedTables, 'Jumlah tabel yang diperiksa');
        $this->assertGreaterThan(150, $checkedRows, 'Jumlah baris yang diperiksa');
    }

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
