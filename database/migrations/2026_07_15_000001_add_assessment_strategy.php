<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->decimal('minimum_passing_grade', 4, 2)->default(6);
        });

        Schema::table('classrooms', function (Blueprint $table): void {
            $table->string('assessment_mode')->default('numeric');
        });

        Schema::create('concept_scales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('color')->default('gray');
            $table->boolean('is_passing')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::table('grades', function (Blueprint $table): void {
            $table->decimal('grade_1', 5, 2)->nullable()->after('grade');
            $table->decimal('grade_2', 5, 2)->nullable()->after('grade_1');
            $table->decimal('recovery_grade', 5, 2)->nullable()->after('grade_2');
            $table->foreignId('concept_scale_id')->nullable()->after('recovery_grade')->constrained()->nullOnDelete();
            $table->decimal('grade', 5, 2)->nullable()->change();
        });

        DB::table('grades')->whereNotNull('grade')->update([
            'grade_1' => DB::raw('grade'),
            'grade_2' => DB::raw('grade'),
        ]);

        $now = now();
        DB::table('companies')->pluck('id')->each(function (int $companyId) use ($now): void {
            DB::table('concept_scales')->insert([
                ['company_id' => $companyId, 'code' => 'A', 'label' => 'Excelente', 'sort_order' => 1, 'color' => 'success', 'is_passing' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'code' => 'B', 'label' => 'Bom', 'sort_order' => 2, 'color' => 'info', 'is_passing' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'code' => 'C', 'label' => 'Regular', 'sort_order' => 3, 'color' => 'warning', 'is_passing' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'code' => 'D', 'label' => 'Insuficiente', 'sort_order' => 4, 'color' => 'danger', 'is_passing' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        });
    }

    public function down(): void
    {
        DB::table('grades')->whereNull('grade')->delete();
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('concept_scale_id');
            $table->dropColumn(['grade_1', 'grade_2', 'recovery_grade']);
            $table->decimal('grade', 5, 2)->nullable(false)->change();
        });
        Schema::dropIfExists('concept_scales');
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('assessment_mode'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('minimum_passing_grade'));
    }
};
