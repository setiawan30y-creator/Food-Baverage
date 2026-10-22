<?php

namespace App\Http\Controllers\Public;

use App\Domain\Catalog\MenuStockService;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;

/** Bantuan bersama halaman pelanggan. @2026-Alifiandra */
trait PublicMenu
{
    protected function bootTenant(Tenant $tenant, ?Branch $branch = null): void
    {
        abort_unless($tenant->isUsable(), 404);
        tenancy()->setTenant($tenant);
        if ($branch) {
            abort_unless($branch->tenant_id === $tenant->id && $branch->is_active, 404);
            tenancy()->setBranch($branch);
        }
    }

    protected function menuPayload(Branch $branch): array
    {
        $settings = BranchMenu::where('branch_id', $branch->id)->get()->keyBy('menu_id');
        $menus = Menu::with(['modifiers' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($m) => (! ($s = $settings->get($m->id)) || $s->is_available));
        $stock = app(MenuStockService::class)->forBranch($branch, $menus);
        $menus = $menus
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'category' => $m->menu_category_id,
                'price' => (float) ($settings->get($m->id)?->price ?? $m->price),
                'stock' => $stock[$m->id]['stock'],
                'sold_out' => (bool) ($settings->get($m->id)?->sold_out) || ! $m->isScheduledAt(now()) || ($branch->block_out_of_stock && $stock[$m->id]['stock'] !== null && $stock[$m->id]['stock'] <= 0),
                'photo' => $m->photoUrl(),
                'modifiers' => $m->modifiers->map(fn ($x) => ['id' => $x->id, 'group' => $x->group_name, 'name' => $x->name, 'price' => (float) $x->price])->values(),
            ])->values();

        return [
            'menus' => $menus,
            'categories' => MenuCategory::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.note' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ];
    }
}
