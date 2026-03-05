<?php

use App\Models\Merchant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $merchants = Merchant::all();

        foreach ($merchants as $merchant) {
            $gatewaySettings = [];

            $merchant->update(['gateway_settings' => $gatewaySettings]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
