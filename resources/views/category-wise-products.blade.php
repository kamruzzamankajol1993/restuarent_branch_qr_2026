@extends('master')

@section('title')
    {{ $category->name ?? 'Category' }} - Menu
@endsection

@section('css')
@endsection

@section('body')

<div class="rapp-menu-toolbar">
    <div class="rapp-search-bar">
      <i class="bi bi-search"></i>
      <input type="text" class="rapp-search-input" value="{{ request('search') }}" aria-label="Search dishes" placeholder="Search for dishes…">
    </div>
    <div class="rapp-menu-cats">
      <a href="{{ url('menu_page/'.$table_id) }}" class="rapp-menu-cat-tab">
        <span class="rapp-menu-cat-tab-emoji">🍽️</span>All
      </a>
      @foreach($globalCategories as $cat)
      <a href="{{ url('category_wise_products/'.$cat->id.'/'.$table_id) }}" class="rapp-menu-cat-tab {{ (isset($category) && $category->id == $cat->id) ? 'active' : '' }}">
        <span class="rapp-menu-cat-tab-emoji">
             @if($cat->image)
                <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/categories/' . $cat->image : asset('public/uploads/categories/' . $cat->image) }}" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">
             @else
                🍛
             @endif
        </span>{{ $cat->name }}
      </a>
      @endforeach
    </div>
  </div>

  <main class="rapp-main rapp-main-menu">
    <div id="menuFoodGrid">

      <div class="rapp-cat-group-head">
        <span class="rapp-cat-group-emoji">
             @if($category->image)
                <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/categories/' . $category->image : asset('public/uploads/categories/' . $category->image) }}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; vertical-align: middle;">
             @else
                🍛
             @endif
        </span>
        <span class="rapp-cat-group-name">{{ $category->name }}</span>
        <span class="rapp-cat-group-count">{{ $foodItems->total() }} items</span>
      </div>

      <div class="rapp-food-grid" id="append-food-data" data-next-page="{{ $foodItems->nextPageUrl() }}">
        @foreach($foodItems as $food)
        <div class="rapp-food-card" data-food-id="{{ $food->id }}" data-cat-id="{{ $category->id }}">
          <div class="rapp-food-img">
            @if($food->main_image)
               <img src="{{ $restaurantSettingappLink ? rtrim($restaurantSettingappLink, '/') . '/public/uploads/foods/' . $food->main_image : asset('public/uploads/foods/' . $food->main_image) }}" alt="{{ $food->name }}" loading="lazy">
            @else
               <span class="rapp-food-img-fallback">🍛</span>
            @endif

            @if($food->is_featured)
            <span class="rapp-food-badge">⭐ Popular</span>
            @endif
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
        @endforeach
        @if($foodItems->isEmpty())
        <div class="px-3 w-100 text-center text-muted">
          <p>No matching dishes found in this category.</p>
        </div>
        @endif
      </div>

      <div class="ajax-load text-center" style="display:none; padding: 20px 0;">
          <div class="spinner-border text-success spinner-border-sm" role="status"></div>
          <span style="font-size: 13px; color: #777; margin-left: 5px;">Loading more...</span>
      </div>

      <div style="height:12px;"></div>
    </div>
  </main>
@endsection

@section('script')
@include('include.menu-search-scroll')
@endsection
