<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\FoodCategory;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use App\Models\Waiter;
use App\Services\QrBranchSettingResolver;
use App\Services\QrTableContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            $settings = app(QrBranchSettingResolver::class);
            $mainBranchId = $settings->mainBranchId();

            // Safe defaults for non-table pages. QR table pages override these below.
            View::share($this->branchViewData($mainBranchId));
            View::share('customers', Schema::hasTable('customers') ? Customer::query()->orderBy('name')->get() : collect());

            View::composer('*', function ($view) use ($settings) {
                $reference = request()->route('table_id');
                if (!$reference) {
                    return;
                }

                try {
                    $table = app(QrTableContext::class)->resolve((string) $reference);
                    $branchId = Schema::hasColumn('tables', 'branch_id') ? (int) $table->branch_id : $settings->mainBranchId();
                    $view->with($this->branchViewData($branchId));
                } catch (Throwable $e) {
                    // The route controller owns the authoritative invalid/expired QR response.
                }
            });
        } catch (Throwable $e) {
            // Keep install/migration commands bootable while tables are being created.
        }
    }

    private function branchViewData(?int $branchId): array
    {
        $settings = app(QrBranchSettingResolver::class);
        $restaurant = $settings->get(RestaurantSetting::class, $branchId, $branchId === null);
        $tax = $settings->get(TaxSetting::class, $branchId, $branchId === null);
        $invoice = $settings->get(InvoiceSetting::class, $branchId, $branchId === null);
        $pos = $settings->get(PosSetting::class, $branchId, $branchId === null);

        $categoryQuery = FoodCategory::query()->whereNull('parent_category_id')->where('status', 1);
        if ($branchId && Schema::hasColumn('food_categories', 'branch_id')) {
            $categoryQuery->where('branch_id', $branchId);
        }

        $waiterQuery = Waiter::query()->where('status', 1);
        if ($branchId && Schema::hasColumn('waiters', 'branch_id')) {
            $waiterQuery->where('branch_id', $branchId);
        }

        return [
            'restaurantSettingName' => $restaurant?->name,
            'restaurantSettingPhone' => $restaurant?->phone,
            'restaurantSettingEmail' => $restaurant?->email,
            'restaurantSettingWebsite' => $restaurant?->website,
            'restaurantSettingappLink' => $restaurant?->app_link,
            'restaurantSettingAddress' => $restaurant?->address,
            'restaurantSettingOpeningTime' => $restaurant?->opening_time,
            'restaurantSettingClosingTime' => $restaurant?->closing_time,
            'restaurantSettingCurrency' => $restaurant?->currency ?? 'BDT',
            'restaurantSettingIconName' => $restaurant?->icon_name,
            'restaurantSettingLogo' => $restaurant?->logo,

            'taxSettingVatRate' => $tax?->vat_rate ?? 0,
            'taxSettingTaxLabel' => $tax?->tax_label ?? 'VAT',
            'taxSettingTaxRegistrationNo' => $tax?->tax_registration_no,
            'taxSettingIsTaxIncluded' => (bool) ($tax?->is_tax_included ?? false),
            'taxSettingServiceCharge' => $tax?->service_charge ?? 0,

            'invoiceSettingPrefix' => $invoice?->prefix ?? 'INV-',
            'invoiceSettingStartingNumber' => $invoice?->starting_number ?? 1001,
            'invoiceSettingFooterNote' => $invoice?->footer_note,
            'invoiceSettingPaperSize' => $invoice?->paper_size ?? '80mm',
            'invoiceSettingShowLogo' => (bool) ($invoice?->show_logo ?? true),

            'posSettingDefaultView' => $pos?->default_view ?? 'Grid',
            'posSettingItemsPerPage' => $pos?->items_per_page ?? 12,
            'posSettingAutoPrintKitchen' => (bool) ($pos?->auto_print_kitchen ?? true),
            'posSettingAutoPrintInvoice' => (bool) ($pos?->auto_print_invoice ?? true),
            'posSettingRequireTableSelection' => (bool) ($pos?->require_table_selection ?? true),
            'posSettingShowOutOfStock' => (bool) ($pos?->show_out_of_stock ?? true),

            'restaurantSetting' => $restaurant,
            'taxSetting' => $tax,
            'invoiceSetting' => $invoice,
            'posSetting' => $pos,
            'waiters' => $waiterQuery->get(),
            'globalCategories' => $categoryQuery->get(),
        ];
    }
}
