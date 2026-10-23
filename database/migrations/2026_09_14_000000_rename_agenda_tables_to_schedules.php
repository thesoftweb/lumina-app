<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('agendas', 'schedules');
        Schema::rename('agenda_classroom', 'classroom_schedule');

        Schema::table('classroom_schedule', function (Blueprint $table) {
            $table->renameColumn('agenda_id', 'schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_schedule', function (Blueprint $table) {
            $table->renameColumn('schedule_id', 'agenda_id');
        });

        Schema::rename('classroom_schedule', 'agenda_classroom');
        Schema::rename('schedules', 'agendas');
    }
};
