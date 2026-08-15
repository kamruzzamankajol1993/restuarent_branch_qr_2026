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

        @if($order->status == 'Completed')
        <div class="review-box-area" id="reviewContainer_{{ $order->id }}" style="padding: 15px; border-top: 1px dashed #e0e0e0; background: #f4fdf8; border-radius: 0 0 6px 6px;">
            @if($order->review)
                <div class="text-success" style="font-size: 13px; font-weight: 700;">
                    <i class="bi bi-check-circle-fill"></i> Thank you! Your rating and review have been submitted.
                </div>
            @else
                <label style="font-size: 13px; font-weight: 700; color: #21352a; margin-bottom: 8px; display: block;">
                    Rate your food & experience:
                </label>

                <form class="review-submit-form" data-order-id="{{ $order->id }}">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <input type="hidden" name="rating" id="ratingInput_{{ $order->id }}" value="5"> <div class="star-rating mb-3" data-order-id="{{ $order->id }}" style="font-size: 22px; cursor: pointer; color: #ffc107;">
                        <i class="bi bi-star-fill star-btn" data-val="1"></i>
                        <i class="bi bi-star-fill star-btn" data-val="2"></i>
                        <i class="bi bi-star-fill star-btn" data-val="3"></i>
                        <i class="bi bi-star-fill star-btn" data-val="4"></i>
                        <i class="bi bi-star-fill star-btn" data-val="5"></i>
                    </div>

                    <div class="d-flex gap-2">
                        <input type="text" name="review" id="reviewInput_{{ $order->id }}" class="form-control form-control-sm" placeholder="Write a quick review..." style="font-size: 13px; border: 1px solid #ccc; box-shadow: none;" required>
                        <button type="submit" class="btn rapp-btn-primary btn-sm" style="font-size: 13px; padding: 6px 16px;">Submit</button>
                    </div>
                </form>
            @endif

            <div style="font-size: 11px; color: #888; margin-top: 10px;">
                <i class="bi bi-info-circle"></i> This order history will automatically disappear from the table screen in 5 minutes.
            </div>
        </div>
        @endif

      </div>
      @empty
      <div class="px-3 py-5 text-center text-muted">
          <i class="bi bi-receipt" style="font-size: 3rem; opacity: 0.2;"></i>
          <p class="mt-3">No orders found for this table yet.</p>
          <a href="{{ url('menu_page/'.$table_id) }}" class="btn rapp-btn-primary btn-sm mt-2">Browse Menu</a>
      </div>
      @endforelse

    </div>
</main>
@endsection

@section('script')
<script>
$(document).ready(function() {
    // ==========================================
    // Star Rating Click Logic (Fixed)
    // ==========================================
    $(document).on('click', '.star-btn', function() {
        let val = $(this).data('val');
        let parent = $(this).parent();
        let orderId = parent.data('order-id');

        // Hidden input এ রেটিং ভ্যালু সেট করা
        $('#ratingInput_' + orderId).val(val);

        // সব স্টারকে প্রথমে ফাঁকা (muted) করে দেওয়া
        parent.find('.star-btn').removeClass('bi-star-fill').addClass('bi-star').css('color', '#ccc');

        // যত নাম্বার স্টারে ক্লিক করেছে তত পর্যন্ত ফিল (fill) করা
        parent.find('.star-btn').each(function() {
            if($(this).data('val') <= val) {
                $(this).removeClass('bi-star').addClass('bi-star-fill').css('color', '#ffc107');
            }
        });
    });

    // ==========================================
    // Ajax Review Form Submission (Fixed)
    // ==========================================
    $(document).on('submit', '.review-submit-form', function(e) {
        e.preventDefault();

        let form = $(this);
        let orderId = form.data('order-id');
        let reviewText = $('#reviewInput_' + orderId).val();
        let ratingVal = $('#ratingInput_' + orderId).val(); // Rating value নেওয়া হচ্ছে
        let container = $('#reviewContainer_' + orderId);

        // বাটন লোডিং স্টেট
        let submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('Processing...');

        $.ajax({
            url: "{{ route('review.submit') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                order_id: orderId,
                rating: ratingVal, // রেটিং পাঠানো হচ্ছে
                review: reviewText
            },
            success: function(res) {
                if(res.status === 'success') {
                    // UI dynamic success messaging update without refresh
                    container.html(`
                        <div class="text-success" style="font-size: 13px; font-weight: 700;">
                            <i class="bi bi-check-circle-fill"></i> Thank you! Your rating and review have been submitted.
                        </div>
                        <div style="font-size: 11px; color: #888; margin-top: 10px;">
                            <i class="bi bi-info-circle"></i> This order history will automatically disappear from the table screen in a few minutes.
                        </div>
                    `);
                }
            },
            error: function(xhr) {
                // বাটন আগের অবস্থায় ফিরিয়ে আনা
                submitBtn.prop('disabled', false).html('Submit');

                // আসল এরর মেসেজটি এলার্ট করা
                let errorMessage = 'Something went wrong. Please try again!';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert('Error: ' + errorMessage);
                console.log(xhr.responseText); // কনসোলেও বিস্তারিত এরর দেখাবে
            }
        });
    });
});
</script>
@endsection
