@php
    $decoded_id = $decoded_table_id ?? $decoded_id ?? null;
    $cartCount = $decoded_id ? count(session('web_cart_table_' . $decoded_id, [])) : 0;
@endphp

<header class="rapp-page-header d-flex align-items-center justify-content-between px-3 gap-3">
  <a href="{{ url('home_page/'.$table_id) }}" class="rapp-page-back" title="Back">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="rapp-page-title">Our Menu</div>
  <a href="{{ url('cart_page/'.$table_id) }}" class="rapp-header-btn" title="My Cart" style="position:relative;">
    <i class="bi bi-bag-fill"></i>
    <span class="rapp-cart-badge" style="{{ $cartCount == 0 ? 'display:none;' : '' }}">{{ $cartCount }}</span>
  </a>
</header>
