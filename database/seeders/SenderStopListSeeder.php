<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SenderStopListSeeder extends Seeder
{
    /**
     * Seed the SMS sender stop list that previously lived in the legacy SQL dump.
     */
    public function run(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/data/sender_stop_lists.json'), true);

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('sender_stop_lists')->updateOrInsert(['id' => $row['id']], ['sender' => $row['sender']]);
        }
    }
}
