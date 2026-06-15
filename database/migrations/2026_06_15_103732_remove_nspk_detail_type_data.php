<?php

use App\Models\Merchant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payment_details')
            ->where('detail_type', 'nspk')
            ->update([
                'detail_type' => 'e-com',
                'is_active' => false,
            ]);

        $this->stripNspkFromDetailTypesColumn('payment_gateways');

        if (Schema::hasTable('payout_offers')) {
            $this->stripNspkFromDetailTypesColumn('payout_offers');
        }

        Merchant::query()
            ->select(['id', 'settings'])
            ->chunkById(200, function ($merchants): void {
                foreach ($merchants as $merchant) {
                    $settings = $merchant->settings ?? [];
                    $commissionSettings = $settings['commission_settings'] ?? [];

                    if (! is_array($commissionSettings) || $commissionSettings === []) {
                        continue;
                    }

                    $items = array_is_list($commissionSettings)
                        ? $commissionSettings
                        : array_values($commissionSettings);

                    $filtered = array_values(array_filter(
                        $items,
                        static fn (mixed $item): bool => ! is_array($item)
                            || (($item['detail_type'] ?? null) !== 'nspk')
                    ));

                    if (count($filtered) === count($items)) {
                        continue;
                    }

                    $settings['commission_settings'] = $filtered;
                    $merchant->settings = $settings;
                    $merchant->saveQuietly();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    private function stripNspkFromDetailTypesColumn(string $table): void
    {
        DB::table($table)
            ->whereNotNull('detail_types')
            ->where('detail_types', 'like', '%nspk%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $detailTypes = json_decode((string) $row->detail_types, true);

                    if (! is_array($detailTypes)) {
                        continue;
                    }

                    $filtered = array_values(array_filter(
                        $detailTypes,
                        static fn (mixed $type): bool => $type !== 'nspk'
                    ));

                    if (count($filtered) === count($detailTypes)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['detail_types' => json_encode($filtered)]);
                }
            });
    }
};
