@extends('master')

@section('title') Cart @endsection

@section('body')
<main class="rapp-main" style="padding-top: calc(var(--header-h) + 14px);">
    <div class="rapp-order-wrap">

      <div class="rapp-table-input-card">
        <div class="rapp-table-input-label"><i class="bi bi-layout-wtf"></i> Your Table</div>
        <div class="rapp-table-input-row">
          {{-- কন্ট্রোলার থেকে আসা decoded_id ব্যবহার করা হয়েছে --}}
          <input type="text" class="form-control rapp-table-field" readonly value="Table: {{ $decoded_id }}">
        </div>
      </div>

      <div class="rapp-cart-list" id="cartItemsContainer">
        @forelse($cart as $id => $item)
        <div class="rapp-cart-item d-flex align-items-center gap-3">
          <div class="rapp-cart-item-img">
            @if(isset($item['image']) && $item['image'])
              {{-- আপনার দেওয়া ইমেজ লিঙ্ক লজিক এখানে যুক্ত করা হয়েছে --}}
              <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/foods/' . $item['image'] : asset('public/uploads/foods/' . $item['image']) }}" alt="{{ $item['name'] }}" loading="lazy">
            @else
              <span class="rapp-cart-item-img-fallback">🍛</span>
            @endif
          </div>
          <div class="rapp-cart-item-info">
            <div class="rapp-cart-item-name">{{ $item['name'] }}</div>
            <div class="rapp-cart-item-price">৳{{ number_format($item['price'], 0) }} each</div>
          </div>
          <div class="rapp-cart-item-right d-flex flex-column align-items-end gap-2">
            <div class="rapp-cart-item-total">৳{{ number_format($item['price'] * $item['qty'], 0) }}</div>
            <div class="rapp-cart-inline-qty">
              <button class="rapp-cart-q-btn update-qty-btn" data-id="{{ $id }}" data-action="minus" type="button">−</button>
              <span class="rapp-cart-q-num">{{ $item['qty'] }}</span>
              <button class="rapp-cart-q-btn update-qty-btn" data-id="{{ $id }}" data-action="plus" type="button">+</button>
            </div>
          </div>
        </div>
        @empty
        <div class="text-center text-muted p-4">Your cart is empty!</div>
        @endforelse
      </div>

     <div class="rapp-summary-card">
    <div class="rapp-summary-title">Order Summary</div>
    <div class="rapp-summary-row">
        <span>Subtotal</span><span>৳{{ number_format($subtotal, 0) }}</span>
    </div>
    <div class="rapp-summary-row">
        <span>Tax ({{ $vat_rate }}%)</span><span>৳{{ number_format($vat_amount, 0) }}</span>
    </div>

    @if($service_charge_rate > 0)
    <div class="rapp-summary-row">
        <span>Service Charge ({{ $service_charge_rate }}%)</span><span>৳{{ number_format($service_amount, 0) }}</span>
    </div>
    @endif

    <div class="rapp-summary-row grand">
        <span>Total</span><span>৳{{ number_format($grand_total, 0) }}</span>
    </div>
</div>

      <div class="rapp-note-card">
        <div class="rapp-note-label"><i class="bi bi-chat-left-text"></i> Special Instructions</div>
        <textarea id="cartNotes" class="form-control rapp-note-textarea" placeholder="Dietary needs, spice level, allergies…"></textarea>
      </div>

      <div class="rapp-order-actions">
        <button class="btn rapp-btn-primary w-100" id="btnPlaceOrder" type="button" {{ count($cart) == 0 ? 'disabled' : '' }}>
          <i class="bi bi-check-circle-fill"></i> Place Order
        </button>
        <button class="btn rapp-btn-outline w-100" id="btnCallWaiter" type="button">
          <i class="bi bi-bell-fill"></i> Call Waiter
        </button>
      </div>

    </div>
</main>
@endsection
