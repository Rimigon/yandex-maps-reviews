<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Отзывы организации. Отдаются из базы постранично, поэтому переключение
 * страниц в интерфейсе не ходит в Яндекс.
 */
class OrganizationReviewController extends Controller
{
    /** Размер страницы зафиксирован заданием. */
    private const PER_PAGE = 50;

    public function index(Organization $organization): AnonymousResourceCollection
    {
        Gate::authorize('view', $organization);

        $reviews = $organization->reviews()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(perPage: self::PER_PAGE);

        return ReviewResource::collection($reviews);
    }
}
