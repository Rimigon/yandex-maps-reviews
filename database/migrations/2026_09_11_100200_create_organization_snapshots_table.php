<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_total')->default(0);
            $table->unsignedInteger('reviews_total')->default(0);
            $table->unsignedInteger('reviews_parsed')->default(0);

            // Что изменилось между этим снимком и предыдущим: было -> стало.
            $table->json('changes')->nullable();

            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['organization_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
