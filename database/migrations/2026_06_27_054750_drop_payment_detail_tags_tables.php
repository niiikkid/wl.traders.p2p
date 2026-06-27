<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_detail_tag_payment_detail');
        Schema::dropIfExists('payment_detail_tags');
    }

    public function down(): void
    {
        // Intentionally empty — tag feature was removed.
    }
};
