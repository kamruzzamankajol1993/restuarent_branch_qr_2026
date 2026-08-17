@php
    // সেশন থেকে ডাটা নিয়ে কার্টের আইটেম গোনা হচ্ছে
    $decoded_id = $decoded_table_id ?? $decoded_id ?? null;
    $cartCount = $decoded_id ? count(session('web_cart_table_' . $decoded_id, [])) : 0;
@endphp

<header class="rapp-header">
    <div class="rapp-header-inner d-flex align-items-center justify-content-between px-3 h-100">
      <div class="rapp-logo d-flex align-items-center gap-2">
  <div class="rapp-logo-icon">
     @if($restaurantSettingLogo)
        <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/' . $restaurantSettingLogo : asset('public/uploads/settings/' . $restaurantSettingLogo) }}" style="width: 40px; height: 40px; border-radius: 50%;">
     @else
        🌿
     @endif
  </div>
  <div>
    <div class="rapp-logo-name">{{ $restaurantSettingName }}</div>
    
  </div>
</div>
      <div class="d-flex align-items-center gap-2">
        <a href="{{ url('cart_page/'.$table_id) }}" class="rapp-header-btn" title="My Cart">
          <i class="bi bi-bag-fill"></i>
          <span class="rapp-cart-badge" style="{{ $cartCount == 0 ? 'display:none;' : '' }}">{{ $cartCount }}</span>
        </a>
      </div>
    </div>
</header>
