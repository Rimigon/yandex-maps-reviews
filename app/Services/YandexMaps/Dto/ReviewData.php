<?php

namespace App\Services\YandexMaps\Dto;

use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Отзыв в том виде, в котором он приходит из внутреннего API Яндекс.Карт.
 */
final class ReviewData
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $authorName,
        public readonly ?string $authorAvatarUrl,
        public readonly ?int $rating,
        public readonly ?string $text,
        public readonly ?string $businessComment,
        public readonly int $likes,
        public readonly int $dislikes,
        public readonly bool $isPinned,
        public readonly ?CarbonInterface $publishedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromApi(array $raw): self
    {
        $externalId = $raw['reviewId'] ?? null;

        if (! is_string($externalId) || $externalId === '') {
            throw LayoutChangedException::forReviews('в отзыве нет поля reviewId');
        }

        $author = is_array($raw['author'] ?? null) ? $raw['author'] : [];
        $comment = is_array($raw['businessComment'] ?? null) ? $raw['businessComment'] : [];
        $reactions = is_array($raw['reactions'] ?? null) ? $raw['reactions'] : [];

        return new self(
            externalId: $externalId,
            authorName: self::string($author['name'] ?? null),
            authorAvatarUrl: self::string($author['avatarUrl'] ?? null),
            rating: is_numeric($raw['rating'] ?? null) ? (int) $raw['rating'] : null,
            text: self::string($raw['text'] ?? null),
            businessComment: self::string($comment['text'] ?? null),
            likes: is_numeric($reactions['likes'] ?? null) ? (int) $reactions['likes'] : 0,
            dislikes: is_numeric($reactions['dislikes'] ?? null) ? (int) $reactions['dislikes'] : 0,
            isPinned: (bool) ($raw['pinned'] ?? false),
            publishedAt: self::date($raw['updatedTime'] ?? null),
        );
    }

    /**
     * Отпечаток содержимого: если он не изменился, отзыв при повторном
     * парсинге не перезаписывается.
     */
    public function contentHash(): string
    {
        return sha1(implode('|', [
            $this->authorName ?? '',
            (string) $this->rating,
            $this->text ?? '',
            $this->businessComment ?? '',
            (string) $this->likes,
            (string) $this->dislikes,
            (string) ($this->isPinned ? 1 : 0),
            $this->publishedAt?->toIso8601String() ?? '',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $publishedAt = $this->publishedAt?->toDateTimeString();

        return [
            'external_id' => $this->externalId,
            'author_name' => $this->authorName,
            'author_avatar_url' => $this->authorAvatarUrl,
            'rating' => $this->rating,
            'text' => $this->text,
            'business_comment' => $this->businessComment,
            'likes' => $this->likes,
            'dislikes' => $this->dislikes,
            'is_pinned' => $this->isPinned,
            'published_at' => $publishedAt,
            'source_updated_at' => $publishedAt,
            'content_hash' => $this->contentHash(),
        ];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
