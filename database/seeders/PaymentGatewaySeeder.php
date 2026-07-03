<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Seed the reference payment gateways that previously lived in the legacy SQL dump.
     */
    public function run(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/data/payment_gateways.json'), true);

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);

            DB::table('payment_gateways')->updateOrInsert(['id' => $id], $row);
        }
    }
}
