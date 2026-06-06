<?php

declare(strict_types=1);

namespace Diffrakt\Core;

/**
 * Validator
 *
 * Rules-based field validator. Pass a rules array and a data array;
 * get back a per-field errors array. Empty errors array means valid.
 *
 * Supported rules:
 *   required                — field must be present and non-empty string
 *   email                   — must pass filter_var FILTER_VALIDATE_EMAIL
 *   min_length: int         — string length must be >= value
 *   max_length: int         — string length must be <= value
 *   integer                 — must be a whole number (int or numeric string)
 *   min: int|float          — numeric value must be >= value
 *   max: int|float          — numeric value must be <= value
 *
 * Usage:
 *
 *   $errors = Validator::validate($_data, [
 *       'username' => ['required', 'min_length:3', 'max_length:32'],
 *       'email'    => ['required', 'email'],
 *       'password' => ['required', 'min_length:8'],
 *       'age'      => ['integer', 'min:18', 'max:120'],
 *   ]);
 *
 *   if (!empty($errors)) {
 *       Response::unprocessable($errors);
 *   }
 */

class Validator {

    public static function validate(array $data, array $rules): array {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $error = self::applyRule($field, $value, $rule, $data);

                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    private static function applyRule(string $field, mixed $value, string $rule, array $data): ?string {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        $label = self::label($field);

        return match ($ruleName) {
            'required' => self::checkRequired($label, $value),
            'email' => self::checkEmail($label, $value),
            'min_length' => self::checkMinLength($label, $value, (int) $param),
            'max_length' => self::checkMaxLength($label, $value, (int) $param),
            'integer' => self::checkInteger($label, $value),
            'min' => self::checkMin($label, $value, (float) $param),
            'max' => self::checkMax($label, $value, (float) $param),
            default => null,
        };
    }

    private static function checkRequired(string $label, mixed $value): ?string {
        if ($value === null || $value === '') {
            return "{$label} is required.";
        }

        return null;
    }

    private static function checkEmail(string $label, mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return "{$label} must be a valid email address.";
        }

        return null;
    }

    private static function checkMinLength(string $label, mixed $value, int $min): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen((string) $value) < $min) {
            return "{$label} must be at least {$min} characters.";
        }

        return null;
    }

    private static function checkMaxLength(string $label, mixed $value, int $max): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen((string) $value) > $max) {
            return "{$label} must not exceed {$max} characters.";
        }

        return null;
    }

    private static function checkInteger(string $label, mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return "{$label} must be a whole number.";
        }

        return null;
    }

    private static function checkMin(string $label, mixed $value, float $min): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return "{$value} must be a number.";
        }

        if ((float) $value < $min) {
            return "{$value} must be at least {$min}.";
        }

        return null;
    }

    private static function checkMax(string $label, mixed $value, float $max): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return "{$value} must be a number.";
        }

        if ((float) $value > $max) {
            return "{$value} must not exceed {$max}.";
        }

        return null;
    }

    private static function label(string $field): string {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
?>