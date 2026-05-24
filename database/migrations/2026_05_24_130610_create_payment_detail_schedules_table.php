<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_detail_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index('user_id');
        });

        Schema::create('payment_detail_schedule_intervals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_detail_schedule_id');
            $table->foreign('payment_detail_schedule_id', 'pdsi_schedule_fk')
                ->references('id')
                ->on('payment_detail_schedules')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(
                ['payment_detail_schedule_id', 'day_of_week'],
                'pdsi_schedule_day_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_detail_schedule_intervals');
        Schema::dropIfExists('payment_detail_schedules');
    }
};
