<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ссылка в том виде, в котором её вставил пользователь, и её
            // нормализованный вариант (без query-параметров карты).
            // Длина 512 — с запасом под реальные адреса карточек и безопасна
            // для уникального индекса (utf8mb4 даёт 2048 байт на 512 символов).
            $table->string('source_url', 2048);
            $table->string('normalized_url', 512);

            // Идентификатор карточки на Яндекс.Картах. Появляется после первой
            // выгрузки: ссылка может быть короткой (/maps/-/xxxx), и тогда id
            // известен только после запроса страницы.
            $table->string('business_id', 32)->nullable();

            $table->string('title')->nullable();
            $table->string('address')->nullable();

            // Рейтинг и счётчики так, как их отдаёт Яндекс.
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_total')->default(0);   // всего оценок
            $table->unsignedInteger('reviews_total')->default(0);   // всего отзывов на карточке
            $table->unsignedInteger('reviews_parsed')->default(0);  // сколько отзывов лежит у нас

            // Прогресс фоновой выгрузки — фронт опрашивает организацию и рисует полосу.
            $table->string('sync_status', 20)->default('idle');
            $table->unsignedSmallInteger('sync_pages_done')->default(0);
            $table->unsignedSmallInteger('sync_pages_total')->default(0);
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
            $table->unique(['user_id', 'normalized_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
