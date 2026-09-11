<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Models\User;
use App\Services\Sync\OrganizationSyncService;
use App\Services\YandexMaps\Exceptions\InvalidYandexMapsUrlException;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Illuminate\Console\Command;
use Throwable;

/**
 * Проверка парсера без браузера: выгрузка идёт синхронно, прогресс и
 * результат видны в консоли.
 */
class SyncOrganizationCommand extends Command
{
    protected $signature = 'yandex:sync
        {url : Ссылка на карточку организации в Яндекс.Картах}
        {--user= : ID пользователя, за которым закрепить организацию}';

    protected $description = 'Синхронно выгружает отзывы и рейтинг организации с Яндекс.Карт';

    public function handle(OrganizationSyncService $sync): int
    {
        try {
            $url = YandexMapsUrl::parse((string) $this->argument('url'));
        } catch (InvalidYandexMapsUrlException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('Не нашёл пользователя. Выполните php artisan db:seed или укажите --user=');

            return self::FAILURE;
        }

        $organization = $user->organizations()->matchingUrl($url)->first()
            ?? $user->organizations()->create([
                'source_url' => $url->normalized,
                'normalized_url' => $url->normalized,
                'business_id' => $url->businessId,
                'sync_status' => SyncStatus::Idle,
            ]);

        $this->info("Координаты карточки: {$url->normalized}");
        $this->line('Выгружаю отзывы (запросы идут с паузами, это занимает ~10 секунд)...');

        try {
            $result = $sync->sync($organization);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $organization->refresh();

        $this->table(
            ['Организация', 'Рейтинг', 'Оценок', 'Отзывов на карточке', 'Загружено'],
            [[
                $organization->title ?? $organization->business_id,
                $organization->rating ?? '—',
                $organization->ratings_total,
                $organization->reviews_total,
                $result->reviewsStored,
            ]],
        );

        $this->info(sprintf(
            'Страниц: %d. Новых отзывов: %d, обновлённых: %d.',
            $result->pagesProcessed,
            $result->reviews->created,
            $result->reviews->updated,
        ));

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');

        return $userId === null
            ? User::query()->orderBy('id')->first()
            : User::query()->find((int) $userId);
    }
}
