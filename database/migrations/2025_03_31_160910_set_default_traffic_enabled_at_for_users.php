<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Устанавливаем значение traffic_enabled_at на неделю назад для всех пользователей,
        // у которых traffic_enabled_at = null
        $oneWeekAgo = Carbon::now()->subWeek();
        
        // Для пользователей с включенным трафиком устанавливаем время на неделю назад
        User::whereNull('traffic_enabled_at')
            ->update([
                'traffic_enabled_at' => $oneWeekAgo
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Если нужно отменить, сбрасываем поле обратно на null
        User::whereNotNull('traffic_enabled_at')
            ->update([
                'traffic_enabled_at' => null
            ]);
    }
};
