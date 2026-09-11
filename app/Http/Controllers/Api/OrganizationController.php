<?php

namespace App\Http\Controllers\Api;

use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OrganizationSnapshotResource;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Экран настроек: список подключённых карточек и добавление новой.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = $request->user()->organizations()
            ->orderByDesc('id')
            ->get();

        return OrganizationResource::collection($organizations);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $source = (string) $request->string('url');
        $url = YandexMapsUrl::parse($source);

        $organization = $request->user()->organizations()->create([
            'source_url' => $source,
            'normalized_url' => $url->normalized,
            'business_id' => $url->businessId,
            'sync_status' => SyncStatus::Idle,
        ]);

        $this->dispatchSync($organization);

        return OrganizationResource::make($organization->refresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Organization $organization): OrganizationResource
    {
        Gate::authorize('view', $organization);

        return OrganizationResource::make($organization);
    }

    /**
     * Перезапуск выгрузки. Без force свежие данные не парсятся повторно:
     * отзывы уже лежат в БД, листание страниц в Яндекс не ходит.
     */
    public function sync(Request $request, Organization $organization): OrganizationResource
    {
        Gate::authorize('update', $organization);

        if ($organization->sync_status->isInProgress()) {
            return OrganizationResource::make($organization);
        }

        if (! $request->boolean('force') && $organization->hasFreshData()) {
            return OrganizationResource::make($organization);
        }

        $this->dispatchSync($organization);

        return OrganizationResource::make($organization->refresh());
    }

    public function snapshots(Organization $organization): AnonymousResourceCollection
    {
        Gate::authorize('view', $organization);

        $snapshots = $organization->snapshots()
            ->orderByDesc('captured_at')
            ->limit(20)
            ->get();

        return OrganizationSnapshotResource::collection($snapshots);
    }

    public function destroy(Organization $organization): Response
    {
        Gate::authorize('delete', $organization);

        $organization->delete();

        return response()->noContent();
    }

    private function dispatchSync(Organization $organization): void
    {
        SyncOrganizationReviewsJob::dispatch($organization->getKey());

        $organization->markQueued();
    }
}
