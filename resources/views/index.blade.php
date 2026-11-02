@extends('master')

@section('title')
  Home
@endsection

@section('css')
@endsection

@section('body')
<main class="rapp-main">

    <section class="rapp-slider-wrap">
      <div class="rapp-slider">
        <div class="rapp-slides">
          <div class="rapp-slide rapp-slide-1">
            <div class="rapp-slide-content">
              <div class="rapp-slide-tag">🌿 Welcome</div>
              <h2 class="rapp-slide-title">Taste of Nature</h2>
              <p class="rapp-slide-sub">Fresh flavors from our forest kitchen</p>
              <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-slide-btn">Explore Menu <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="rapp-slide-deco">🍛</div>
          </div>
          <div class="rapp-slide rapp-slide-2">
            <div class="rapp-slide-content">
              <div class="rapp-slide-tag">🌾 Daily Fresh</div>
              <h2 class="rapp-slide-title">Farm to Table</h2>
              <p class="rapp-slide-sub">Handpicked ingredients every single day</p>
              <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-slide-btn">Today's Special <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="rapp-slide-deco">🍗</div>
          </div>
        </div>
        <div class="rapp-slide-dots">
          <span class="rapp-dot active"></span>
          <span class="rapp-dot"></span>
        </div>
      </div>
    </section>

    {{-- <div class="d-flex flex-wrap gap-2 px-3 pt-3">
      <div class="rapp-info-chip"><i class="bi bi-clock-fill"></i> Open 11am – 11pm</div>
      <div class="rapp-info-chip"><i class="bi bi-geo-alt-fill"></i> {{ $restaurantSettingAddress }}</div>
      <div class="rapp-info-chip"><i class="bi bi-star-fill"></i> 4.8 Rating</div>
    </div>

    <div class="rapp-nature-banner mx-3 mt-3">
      <div class="rapp-banner-icon">🌾</div>
      <div class="rapp-banner-text">
        <div class="rapp-banner-title">Fresh Every Day</div>
        <div class="rapp-banner-sub">All ingredients sourced from local farms</div>
      </div>
      <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-banner-btn ms-auto">Order Now</a>
    </div> --}}

    <section class="rapp-section">
      <div class="rapp-section-head d-flex align-items-start justify-content-between px-3 pb-2">
        <h3 class="rapp-section-title">Categories</h3>
        <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-see-all mt-1">See All</a>
      </div>
      <div class="rapp-cats-strip">
        <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-cat-pill active">
          <span class="rapp-cat-pill-emoji">🍽️</span>All
        </a>
        @foreach($globalCategories as $cat)
        <a href="{{ url('category_wise_products/'.$cat->id.'/'.$table_id) }}" class="rapp-cat-pill">
          <span class="rapp-cat-pill-emoji">
             @if($cat->image)
                <img src="{{ rtrim($restaurantSettingappLink, '/') . '/public/uploads/categories/' . $cat->image }}" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">
             @else
                🍛
             @endif
          </span>{{ $cat->name }}
        </a>
        @endforeach
      </div>
    </section>

    <section class="rapp-section pb-2">
      <div class="rapp-section-head d-flex align-items-start justify-content-between px-3 pb-2">
        <h3 class="rapp-section-title">🌟 Popular Items</h3>
      </div>
      <div class="rapp-food-grid" id="featuredFoodsGrid" data-next-page="{{ $featuredFoods->nextPageUrl() }}">
        @forelse($featuredFoods as $food)
        <div class="rapp-food-card" data-food-id="{{ $food->id }}" data-cat-id="{{ $food->food_category_id }}">
          <div class="rapp-food-img">
            @if($food->main_image)
               <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/foods/' . $food->main_image : asset('public/uploads/foods/' . $food->main_image) }}" alt="{{ $food->name }}" loading="lazy">
            @else
               <span class="rapp-food-img-fallback">🍛</span>
            @endif
            <span class="rapp-food-badge">⭐ Popular</span>
          </div>
          <div class="rapp-food-body">
            <div class="rapp-food-name">{{ $food->name }}</div>
            <div class="rapp-food-footer">
             <div class="rapp-food-price">
    {{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($food->discount_price > 0 ? $food->discount_price : $food->base_price, 0) }}
    @if($food->discount_price > 0 && $food->discount_price < $food->base_price)
        <del style="font-size: 11px; color: #999; margin-left: 5px;">{{ number_format($food->base_price, 0) }}</del>
    @endif
</div>
              <div class="rapp-food-add-wrap">
                <button class="rapp-add-btn" type="button">+</button>
                <div class="rapp-qty-ctrl">
                  <button class="rapp-qty-btn" type="button">−</button>
                  <span class="rapp-qty-num">0</span>
                  <button class="rapp-qty-btn" type="button">+</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        @empty
        <div class="px-3 w-100 text-center text-muted">
            <p>No popular items found right now.</p>
        </div>
        @endforelse
      </div>
    </section>

  </main>
@endsection

@section('script')
<script>
(function() {
    const featuredFoodsGrid = document.getElementById('featuredFoodsGrid');

    if (!featuredFoodsGrid) {
        return;
    }

    let nextPageUrl = featuredFoodsGrid.dataset.nextPage || null;
    let isLoading = false;

    async function loadMoreFeaturedFoods() {
        if (isLoading || !nextPageUrl) {
            return;
        }

        isLoading = true;

        try {
            const response = await fetch(nextPageUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load featured foods.');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextGrid = doc.getElementById('featuredFoodsGrid');

            if (!nextGrid) {
                nextPageUrl = null;
                return;
            }

            nextGrid.querySelectorAll('.rapp-food-card').forEach(function(card) {
                featuredFoodsGrid.appendChild(card);
            });

            nextPageUrl = nextGrid.dataset.nextPage || null;
            featuredFoodsGrid.dataset.nextPage = nextPageUrl || '';

            if (nextPageUrl) {
                requestAnimationFrame(handleFeaturedFoodsScroll);
            }
        } catch (error) {
            nextPageUrl = null;
            featuredFoodsGrid.dataset.nextPage = '';
            console.error(error);
        } finally {
            isLoading = false;
        }
    }

    function handleFeaturedFoodsScroll() {
        if (!nextPageUrl || isLoading) {
            return;
        }

        const scrollBottom = window.innerHeight + window.scrollY;
        const triggerPoint = document.documentElement.scrollHeight - 300;

        if (scrollBottom >= triggerPoint) {
            loadMoreFeaturedFoods();
        }
    }

    window.addEventListener('scroll', handleFeaturedFoodsScroll, { passive: true });
    handleFeaturedFoodsScroll();
})();
</script>
@endsection
