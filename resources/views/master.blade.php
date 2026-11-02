<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#152219">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title') - Progga Nature Dining</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('/') }}public/front/assets/css/app-style.css">
</head>
<body>
<div class="rapp-shell">

  @if(Route::currentRouteName() === 'menu_page')
    @include('include.menuheader')
  @elseif(Route::currentRouteName() === 'cart_page')
    @include('include.cartheader')
  @elseif(Route::currentRouteName() === 'order_page')
    @include('include.orderheader')
  @elseif(Route::currentRouteName() === 'category_wise_products')
    @include('include.menuheader')
  @else
    @include('include.header')
  @endif

  @yield('body')

  <button class="rapp-waiter-fab" type="button" id="btnCallWaiterGlobal">
    <i class="bi bi-bell-fill"></i>
    Call Waiter
  </button>

  @include('include.footer')

</div>

<a href="#catOffcanvas" class="rapp-cat-fab" title="Browse Categories">
  <i class="bi bi-grid-fill"></i>
  Categories
</a>

<div class="rapp-cat-offcanvas" id="catOffcanvas">
  <div class="rapp-cat-oc-handle"></div>
  <div class="rapp-cat-oc-header">
    <div>
      <div class="rapp-cat-oc-title">Browse Categories</div>
      <div class="rapp-cat-oc-sub">Tap a category to explore</div>
    </div>
    <a href="#close" class="rapp-cat-oc-close"><i class="bi bi-x-lg"></i></a>
  </div>
  <div class="rapp-cat-oc-body">
    <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-cat-oc-item" data-cat-id="0">
      <div class="rapp-cat-oc-emoji">🍽️</div>
      <div class="rapp-cat-oc-info">
        <div class="rapp-cat-oc-name">All Items</div>
      </div>
      <i class="bi bi-chevron-right rapp-cat-oc-arrow"></i>
    </a>

    @foreach($globalCategories as $cat)
    <a href="{{ url('category_wise_products/'.$cat->id.'/'.$table_id) }}" class="rapp-cat-oc-item" data-cat-id="{{ $cat->id }}">
      <div class="rapp-cat-oc-emoji">
          @if($cat->image)
            <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/categories/' . $cat->image : asset('public/uploads/categories/' . $cat->image) }}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%;">
          @else
            🍛
          @endif
      </div>
      <div class="rapp-cat-oc-info">
        <div class="rapp-cat-oc-name">{{ $cat->name }}</div>
        <div class="rapp-cat-oc-count">{{ $cat->food_items_count }} items</div>
      </div>
      <i class="bi bi-chevron-right rapp-cat-oc-arrow"></i>
    </a>
    @endforeach

  </div>
</div>
<a href="#close" class="rapp-cat-backdrop" id="catBackdrop"></a>

<div class="rapp-toast" id="rappToast">
  <div class="rapp-toast-inner">
    <span class="rapp-toast-icon">🔔</span>
    <div>
      <div class="rapp-toast-title">Waiter Notified!</div>
      <div class="rapp-toast-sub">Your waiter will be with you shortly.</div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // AJAX CSRF Token Setup
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // Current Table ID
    const currentTableId = "{{ $table_id ?? '' }}";

    function showSweetAlert(icon, title, message) {
        Swal.fire({
            icon: icon,
            title: title,
            text: message,
            confirmButtonText: 'OK'
        });
    }

    // Display current server-side cart quantities without changing the existing card design.
    const qrInitialCart = {{ \Illuminate\Support\Js::from(session('web_cart_table_' . ($decoded_table_id ?? $decoded_id ?? ''), [])) }};
    const qrQty = {};
    Object.values(qrInitialCart || {}).forEach(item => {
        if (item && item.food_id) qrQty[String(item.food_id)] = Number(item.qty) || 0;
    });

    function updateFoodCard(card, qty) {
        const wrap = card.find('.rapp-food-add-wrap');
        const hasItems = qty > 0;
        wrap.find('.rapp-add-btn').toggle(!hasItems);
        wrap.find('.rapp-qty-ctrl').css('display', hasItems ? 'flex' : 'none');
        wrap.find('.rapp-qty-num').text(qty);
    }
    window.qrRefreshFoodQuantities = function () {
        $('.rapp-food-card').each(function () {
            const card = $(this);
            updateFoodCard(card, qrQty[String(card.data('food-id'))] || 0);
        });
    };
    window.qrRefreshFoodQuantities();

    function updateCartBadges(response) {
        $('.rapp-cart-badge, .rapp-nav-badge')
            .text(response.cart_count)
            .toggle(Number(response.cart_count) > 0);
    }

    // 1. Add to Cart
    $(document).on('click', '.rapp-add-btn', function() {
        let card = $(this).closest('.rapp-food-card');
        let foodId = card.data('food-id');

        if(!foodId) {
            showSweetAlert('error', 'Food ID Missing', 'Please add data-food-id to the card.');
            return;
        }

        let btn = $(this);
        btn.prop('disabled', true).html('...');

        $.post("{{ url('/add-to-cart') }}", { table_id: currentTableId, food_id: foodId }, function(res) {
            btn.prop('disabled', false).html('+');

            if(res.status === 'success') {
                qrQty[String(foodId)] = Number(res.item_qty) || 1;
                updateFoodCard(card, qrQty[String(foodId)]);
                updateCartBadges(res);

                $('#rappToast .rapp-toast-icon').text('🛒');
                $('#rappToast .rapp-toast-title').text('Added to Cart');
                $('#rappToast .rapp-toast-sub').text('Item successfully added.');
                $('#rappToast').addClass('show');
                setTimeout(() => $('#rappToast').removeClass('show'), 3000);
            } else {
                showSweetAlert('error', 'Failed', res.message || 'Failed to add item.');
            }
        }).fail(function(xhr) {
            btn.prop('disabled', false).html('+');
            showSweetAlert('error', 'Unavailable', xhr.responseJSON?.message || 'Failed to add item. Please try again.');
        });
    });

    // Quantity buttons on home/menu/category cards (also works for AJAX-loaded cards).
    $(document).on('click', '.rapp-qty-btn', function () {
        const button = $(this);
        const card = button.closest('.rapp-food-card');
        const foodId = card.data('food-id');
        const action = button.index() === 0 ? 'minus' : 'plus';
        const controls = card.find('.rapp-qty-btn');
        controls.prop('disabled', true);
        $.post("{{ url('/update-cart-qty') }}", {
            table_id: currentTableId, food_id: foodId, action: action
        }).done(function (res) {
            if (res.status === 'success') {
                qrQty[String(foodId)] = Math.max(0, Number(res.item_qty) || 0);
                updateFoodCard(card, qrQty[String(foodId)]);
                updateCartBadges(res);
            } else {
                showSweetAlert('error', 'Unavailable', res.message || 'Please refresh the menu.');
            }
        }).fail(function (xhr) {
            showSweetAlert('error', 'Unavailable', xhr.responseJSON?.message || 'Please refresh the menu.');
        }).always(function () { controls.prop('disabled', false); });
    });

    // 2. Cart Page: Update Quantity
    $(document).on('click', '.update-qty-btn', function() {
        let foodId = $(this).data('id');
        let action = $(this).data('action');

        let btn = $(this);
        btn.prop('disabled', true);

        $.post("{{ url('/update-cart-qty') }}", {
            table_id: currentTableId,
            food_id: foodId,
            action: action
        }, function(res) {
            if(res.status === 'success') {
                location.reload();
            } else {
                btn.prop('disabled', false);
                showSweetAlert('error', 'Failed', res.message || 'Quantity update failed.');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            showSweetAlert('error', 'Failed', 'Quantity update failed. Please try again.');
        });
    });

    // 3. Cart Page: Place Order
    $(document).on('click', '#btnPlaceOrder', function() {
        let notes = $('#cartNotes').val();
        let btn = $(this);

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Placing Order...');

        $.post("{{ url('/checkout-web') }}", {
            table_id: currentTableId,
            notes: notes
        }, function(res) {
            if(res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Order Placed',
                    text: res.message || 'Your order has been placed successfully.',
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.location.href = "{{ url('/order_page') }}/" + currentTableId;
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Order Not Submitted',
                    text: res.message || 'Order could not be submitted.',
                    confirmButtonText: 'OK'
                });

                btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Place Order');
            }
        }).fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Something went wrong',
                text: 'Please try again.',
                confirmButtonText: 'OK'
            });

            btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Place Order');
        });
    });

    // 4. Call Waiter
    $(document).on('click', '.rapp-waiter-fab, #btnCallWaiter, #btnCallWaiterGlobal', function() {
        let btn = $(this);
        let originalHtml = btn.html();

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ...');

        $.post("{{ url('/call-waiter') }}", {
            table_id: currentTableId
        }, function(res) {
            btn.prop('disabled', false).html(originalHtml);

            if(res.status === 'success') {
                $('#rappToast .rapp-toast-icon').text('🔔');
                $('#rappToast .rapp-toast-title').text('Waiter Notified!');
                $('#rappToast .rapp-toast-sub').text('Your waiter will be with you shortly.');
                $('#rappToast').addClass('show');
                setTimeout(() => $('#rappToast').removeClass('show'), 4000);
            } else {
                showSweetAlert('error', 'Failed', res.message || 'Failed to call waiter.');
            }
        }).fail(function() {
            btn.prop('disabled', false).html(originalHtml);
            showSweetAlert('error', 'Failed', 'Failed to call waiter. Check your connection.');
        });
    });
</script>

@yield('script')
</body>
</html>
