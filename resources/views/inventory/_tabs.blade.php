<div class="tabs">
  <a href="{{ route('inventory.stock') }}" class="{{ request()->routeIs('inventory.stock', 'inventory.ledger') ? 'active' : '' }}">Saldo stok</a>
  <a href="{{ route('inventory.items.index') }}" class="{{ request()->routeIs('inventory.items.*') ? 'active' : '' }}">Master bahan</a>
  <a href="{{ route('inventory.waste.index') }}" class="{{ request()->routeIs('inventory.waste.*') ? 'active' : '' }}">Waste / susut</a>
  <a href="{{ route('inventory.counts.index') }}" class="{{ request()->routeIs('inventory.counts.*') ? 'active' : '' }}">Stock opname</a>
  <a href="{{ route('inventory.opening') }}" class="{{ request()->routeIs('inventory.opening') ? 'active' : '' }}">Saldo awal</a>
</div>
