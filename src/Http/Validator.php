<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Validation des entrées côté serveur. Les contrôles du navigateur ne
 * remplacent jamais ceux-ci.
 */
final class Validator
{
    /** @return array<string,mixed> */
    public static function payload(Request $request): array
    {
        return $request->json();
    }

    public static function requiredString(
        array $data,
        string $key,
        string $label,
        int $max = 255
    ): string {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new ApiException(400, 'Données invalides.', [$key => "$label est obligatoire."]);
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new ApiException(400, 'Données invalides.', [$key => "$label est trop long (max $max)."]);
        }
        return $value;
    }

    public static function optionalString(array $data, string $key, int $max = 255): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(400, 'Données invalides.', [$key => 'Valeur textuelle attendue.']);
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new ApiException(400, 'Données invalides.', [$key => "Valeur trop longue (max $max)."]);
        }
        return $value;
    }

    public static function positiveInt(array $data, string $key, string $label): int
    {
        $value = $data[$key] ?? null;
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < 1) {
            throw new ApiException(400, 'Données invalides.', [$key => "$label doit être un entier positif."]);
        }
        return $value;
    }

    public static function oneOf(array $data, string $key, array $allowed, string $label): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ApiException(
                400,
                'Données invalides.',
                [$key => "$label doit valoir : " . implode(', ', $allowed) . '.']
            );
        }
        return $value;
    }

    /** Date/heure au format « Y-m-d H:i » ou ISO « Y-m-d\TH:i », convertie en SQL. */
    public static function dateTime(array $data, string $key, string $label): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ApiException(400, 'Données invalides.', [$key => "$label est obligatoire."]);
        }

        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) !== 1) {
            throw new ApiException(400, 'Données invalides.', [$key => "$label doit être au format AAAA-MM-JJ HH:MM."]);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ApiException(400, 'Données invalides.', [$key => "$label est une date inexistante."]);
        }

        return $date->format('Y-m-d H:i:s');
    }
}
