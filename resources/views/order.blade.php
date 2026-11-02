@extends('master')

@section('title')
    Order History
@endsection

@section('body')
<main class="rapp-main" style="padding-top: calc(var(--header-h) + 14px);">
    <div class="rapp-olist-wrap">

      @forelse($orders as $order)
      <div class="rapp-olist-card">
        <div class="rapp-olist-head">
          <div class="rapp-olist-num">#{{ $order->order_number }}</div>

          @php
            $statusClass = strtolower($order->status);
            $displayStatus = $order->status;

            if($order->status == 'QR_Pending') {
                $statusClass = 'preparing';
                $displayStatus = 'Waiting for Approval';
            } elseif($order->status == 'Pending') {
                $statusClass = 'preparing';
                $displayStatus = 'Pending';
            } elseif($order->status == 'Cooking') {
                $statusClass = 'preparing';
                $displayStatus = 'Cooking';
            } elseif($order->status == 'Completed') {
                $statusClass = 'delivered';
                $displayStatus = 'Completed';
            } elseif($order->status == 'Cancelled') {
                $statusClass = 'cancelled';
                $displayStatus = 'Cancelled';
            }
          @endphp

          <span class="rapp-olist-status {{ $statusClass }}">
            <i class="bi bi-clock-fill"></i> {{ $displayStatus }}
          </span>
        </div>

        <div class="rapp-olist-meta">
          <div class="rapp-olist-meta-item">
            <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($order->order_time)->format('M d, Y · h:i A') }}
          </div>
          <div class="rapp-olist-meta-item">
            <i class="bi bi-layout-wtf"></i> Table: {{ $decoded_id }}
          </div>
        </div>

        <div class="rapp-olist-items">
          @foreach($order->orderDetails as $item)
          <div class="rapp-olist-item-row">
            <div class="rapp-olist-item-thumb">
              <span class="rapp-olist-item-thumb-fallback">🍛</span>
            </div>
            <div class="rapp-olist-item-info">
              <div class="rapp-olist-item-name">{{ $item->product_name }}</div>
              <div class="rapp-olist-item-sub">x{{ $item->quantity }} &nbsp;·&nbsp; {{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($item->price, 0) }} each</div>
            </div>
            <div class="rapp-olist-item-line">{{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($item->subtotal, 0) }}</div>
          </div>
          @endforeach
        </div>

        @if($order->notes)
        <div class="rapp-olist-note">
          <i class="bi bi-chat-quote"></i>&nbsp;{{ $order->notes }}
        </div>
        @endif

        <div class="rapp-olist-breakdown" style="padding: 12px 15px; border-top: 1px solid #f0f0f0; font-size: 13px; background: #fafafa;">
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Subtotal</span>
                <span style="font-weight: 500;">{{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($order->subtotal, 0) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">VAT</span>
                <span style="font-weight: 500;">{{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($order->vat_tax, 0) }}</span>
            </div>
            @if($order->service_charge > 0)
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Service Charge</span>
                <span style="font-weight: 500;">{{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($order->service_charge, 0) }}</span>
            </div>
            @endif
        </div>

        <div class="rapp-olist-footer">
          <div>
            <div class="rapp-olist-total-lbl">Total Amount</div>
            <div class="rapp-olist-total-amt">{{ $restaurantSettingCurrency ?? '৳' }}{{ number_format($order->grand_total, 0) }}</div>
          </div>
        </div>

        @php
            $feedbackToken = trim((string) ($order->feedback_token ?? ''));
            $feedbackUrl = $feedbackToken !== '' ? url('/home_page/feedback/' . rawurlencode($feedbackToken)) : null;
            $structuredFeedbackSubmitted = $order->review && !is_null($order->review->overall_experience_rating);
        @endphp
        <div class="feedback-qr-area" style="padding: 14px 15px; border-top: 1px dashed #e0e0e0; background: #fffdf4; text-align: center;">
            @if($structuredFeedbackSubmitted)
                <div class="text-success" style="font-size: 13px; font-weight: 800;">
                    <i class="bi bi-check-circle-fill"></i> Feedback submitted for this order.
                </div>
            @elseif($feedbackUrl)
                <div style="font-size: 13px; font-weight: 800; color: #21352a; margin-bottom: 8px;">
                    Scan QR Code to Share Feedback
                </div>
                <div
                    class="feedback-order-qr d-inline-flex align-items-center justify-content-center bg-white"
                    data-url="{{ $feedbackUrl }}"
                    style="width: 112px; height: 112px; padding: 7px; border: 1px solid #ded7ba;"
                ></div>
                <div class="mt-2">
                    <a href="{{ $feedbackUrl }}" style="font-size: 12px; font-weight: 700; color: #21352a; text-decoration: underline;">Open Feedback Form</a>
                </div>
            @else
                <div class="text-muted" style="font-size:12px;font-weight:700;">Feedback QR will be available after the feedback database migration is applied.</div>
            @endif
        </div>

      </div>
      @empty
      <div class="px-3 py-5 text-center text-muted">
          <i class="bi bi-receipt" style="font-size: 3rem; opacity: 0.2;"></i>
          <p class="mt-3">No order receipt is available in this browser session.</p>
          <a href="{{ url('menu_page/'.$table_id) }}" class="btn rapp-btn-primary btn-sm mt-2">Browse Menu</a>
      </div>
      @endforelse

    </div>
</main>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.feedback-order-qr').forEach(function (element) {
        if (!element.dataset.url || typeof QRCode === 'undefined') {
            return;
        }

        new QRCode(element, {
            text: element.dataset.url,
            width: 96,
            height: 96,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    });
});
</script>
@endsection
