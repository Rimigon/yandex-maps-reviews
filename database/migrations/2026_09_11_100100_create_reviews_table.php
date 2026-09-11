<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // reviewId из Яндекса — по нему отзыв обновляется, а не дублируется.
            $table->string('external_id', 64);

            $table->string('author_name')->nullable();
            $table->string('author_avatar_url', 512)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->text('business_comment')->nullable();
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('dislikes')->default(0);
            $table->boolean('is_pinned')->default(false);

            // Дата, которую отдаёт Яндекс. Отдельной даты создания отзыва в ответе
            // нет, есть только updatedTime — время последнего изменения, его и
            // показываем как дату отзыва (см. README).
            $table->timestamp('source_updated_at')->nullable();

            // Отпечаток изменяемых полей: по нему видно, изменился ли отзыв
            // при повторном парсинге (идемпотентность + история изменений).
            $table->char('content_hash', 40);

            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'source_updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
