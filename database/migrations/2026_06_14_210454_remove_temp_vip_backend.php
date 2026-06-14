<?php

use App\Models\PaymentDetail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        PaymentDetail::query()
            ->whereIn('user_id', User::query()->where('is_vip', false)->select('id'))
            ->update([
                'min_order_amount' => null,
                'max_order_amount' => null,
            ]);

        Setting::query()
            ->whereIn('key', [
                'temp_vip_required_deals',
                'temp_vip_duration_minutes',
                'temp_vip_enabled',
            ])
            ->delete();

        Schema::dropIfExists('user_temp_vip_activations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'temp_vip_active_until',
                'temp_vip_can_activate',
                'temp_vip_progress_start_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('temp_vip_active_until')->nullable()->after('is_vip');
            $table->boolean('temp_vip_can_activate')->default(false)->after('temp_vip_active_until');
            $table->timestamp('temp_vip_progress_start_at')->nullable()->after('temp_vip_can_activate');
        });

        Schema::create('user_temp_vip_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('activated_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Setting::firstOrCreate(['key' => 'temp_vip_required_deals'], ['value' => 30]);
        Setting::firstOrCreate(['key' => 'temp_vip_duration_minutes'], ['value' => 120]);
        Setting::firstOrCreate(['key' => 'temp_vip_enabled'], ['value' => 0]);
    }
};
