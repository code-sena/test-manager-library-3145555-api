<?php

declare(strict_types=1);

namespace App\Library\Domain;

use App\Library\Domain\Errors\ValidationError;
use DateTimeImmutable;
use DateTimeZone;

final class Guard
{
    public static function text(string $field, string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) {
            throw new ValidationError($field, 'El campo es obligatorio y debe respetar su longitud máxima.');
        }

        return $value;
    }

    public static function uuid(string $value, string $field = 'id'): string
    {
        $value = strtolower($value);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $value) !== 1) {
            throw new ValidationError($field, 'El identificador debe ser un UUID v4.');
        }

        return $value;
    }

    public static function utc(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone('UTC'));
    }
}
