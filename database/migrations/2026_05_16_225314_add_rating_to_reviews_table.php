<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // order_id এর পরে rating কলাম যোগ করা হচ্ছে, ডিফল্ট ৫ স্টার থাকবে
            $table->integer('rating')->default(5)->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
