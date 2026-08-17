@php
    $decoded_id = $decoded_table_id ?? $decoded_id ?? null;
    $cartCount = $decoded_id ? count(session('web_cart_table_' . $decoded_id, [])) : 0;
@endphp

<nav class="rapp-bottom-nav">
  <a href="{{ url('home_page/'.$table_id) }}" class="rapp-nav-item {{ request()->is('home_page/*') ? 'active' : '' }}">
    <i class="bi bi-house-fill"></i>
    <span>Home</span>
  </a>
  <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-nav-item {{ request()->is('menu_page/*') || request()->is('category_wise_products/*') ? 'active' : '' }}">
    <i class="bi bi-grid-fill"></i>
    <span>Menu</span>
  </a>
  <a href="{{ url('cart_page/'.$table_id) }}" class="rapp-nav-item {{ request()->is('cart_page/*') ? 'active' : '' }}">
    <i class="bi bi-bag-fill"></i>
    <span>Cart</span>
    <span class="rapp-nav-badge" style="{{ $cartCount == 0 ? 'display:none;' : '' }}">{{ $cartCount }}</span>
  </a>
  <a href="{{ url('order_page/'.$table_id) }}" class="rapp-nav-item {{ request()->is('order_page/*') ? 'active' : '' }}">
    <i class="bi bi-receipt"></i>
    <span>Orders</span>
  </a>
</nav>
