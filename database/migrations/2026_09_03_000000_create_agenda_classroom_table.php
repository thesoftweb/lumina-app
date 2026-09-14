<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_classroom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agenda_id', 'classroom_id']);
        });

        // Preserve agendas created before support for multiple classrooms.
        DB::table('agendas')
            ->where('global', false)
            ->whereNotNull('classroom_id')
            ->orderBy('id')
            ->each(function (object $agenda): void {
                DB::table('agenda_classroom')->insertOrIgnore([
                    'agenda_id' => $agenda->id,
                    'classroom_id' => $agenda->classroom_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_classroom');
    }
};
