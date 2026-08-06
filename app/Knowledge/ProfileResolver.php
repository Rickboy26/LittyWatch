<?php
declare(strict_types=1);

namespace LittyWatch\Knowledge;

final class ProfileResolver
{
    public function __construct(private readonly KnowledgeBase $kb) {}

    public function resolve(string $itemKey, string $categoryKey, array $modifiers): array
    {
        $profile = $this->kb->profileForItem($itemKey, $categoryKey);
        $track = $profile['track'] ?? [];
        $relevant = [];
        foreach ($track as $field) {
            if (array_key_exists($field, $modifiers)) {
                $relevant[$field] = $modifiers[$field];
            }
        }

        if (isset($relevant['attribute'])) {
            $attribute = $this->kb->matchAttribute((string)$relevant['attribute']);
            if ($attribute !== null) {
                $relevant['attribute_key'] = $attribute['key'];
                $relevant['attribute'] = $attribute['name'];
            }
        }

        $parts = [$itemKey];
        foreach ($profile['market_key'] ?? [] as $field) {
            if ($field === 'item') continue;
            $value = $relevant[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== false) {
                $parts[] = $field . ':' . mb_strtolower((string)$value);
            }
        }

        return [
            'profile' => $profile,
            'relevant' => $relevant,
            'market_key' => implode('|', $parts),
        ];
    }
}
