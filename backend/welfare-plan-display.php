<?php
declare(strict_types=1);

/** Extract the user-written description from older extended-field storage. */
function welfarePlanDescription(?string $description): ?string
{
    if ($description === null || !str_starts_with($description, '__EXT__:')) return $description;
    $offset = 8;
    while (($separator = strpos($description, '||', $offset)) !== false) {
        $metadata = json_decode(substr($description, 8, $separator - 8), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($metadata)) {
            return substr($description, $separator + 2);
        }
        // A delimiter may appear inside a JSON string, so try the next one.
        $offset = $separator + 2;
    }
    // An incomplete legacy payload must never be shown as a description.
    return '';
}

function welfarePlanForDisplay(array $plan): array
{
    $plan['description'] = welfarePlanDescription($plan['description'] ?? null);
    return $plan;
}
