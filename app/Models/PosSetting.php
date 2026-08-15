<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'auto_print_kitchen'      => 'boolean',
        'auto_print_invoice'      => 'boolean',
        'require_table_selection' => 'boolean',
        'show_out_of_stock'       => 'boolean',
        'items_per_page'          => 'integer',
    ];
}
