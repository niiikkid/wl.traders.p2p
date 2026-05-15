<?php

use App\Enums\NotificationEvent;
use App\Enums\NotificationMessageScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_rules')
            ->where('event', NotificationEvent::MESSAGE_RECEIVED->value)
            ->update(['message_scope' => NotificationMessageScope::ALL->value]);
    }

    public function down(): void
    {
        // Intentionally empty: previous scope values cannot be restored safely.
    }
};
