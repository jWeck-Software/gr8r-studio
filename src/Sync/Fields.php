<?php

namespace App\Sync;

/**
 * Reads the fields of one record from a put and refuses the batch, naming the field,
 * when a value has the wrong shape. Keys the server doesn't know are ignored.
 */
final readonly class Fields
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data,
        private string $record,
    ) {
    }

    /** @param int|null $maxLength the most characters the value may have, when it is stored in a column of that length */
    public function string(string $field, ?int $maxLength = null): string
    {
        $value = $this->required($field);
        if (!\is_string($value)) {
            throw $this->wrong($field, 'a string');
        }

        return null === $maxLength || mb_strlen($value) <= $maxLength ? $value : throw $this->wrong($field, \sprintf('a string of at most %d characters', $maxLength));
    }

    public function nonEmptyString(string $field, ?int $maxLength = null): string
    {
        $value = $this->string($field, $maxLength);

        return '' !== trim($value) ? $value : throw $this->wrong($field, 'a non-empty string');
    }

    /** A colour as the frontend writes it, #RRGGBB. */
    public function colour(string $field): string
    {
        $value = $this->required($field);

        return \is_string($value) && 1 === preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : throw $this->wrong($field, 'a colour like #5A67D8');
    }

    public function nullableString(string $field, int $maxLength = 200): ?string
    {
        $value = $this->required($field);
        if (null === $value || (\is_string($value) && mb_strlen($value) <= $maxLength)) {
            return $value;
        }

        throw $this->wrong($field, \sprintf('a string of at most %d characters or null', $maxLength));
    }

    /** @param list<string> $allowed */
    public function oneOf(string $field, array $allowed): string
    {
        $value = $this->required($field);

        return \in_array($value, $allowed, true) ? $value : throw $this->wrong($field, 'one of '.implode(', ', $allowed));
    }

    /** A calendar date YYYY-MM-DD, or null. */
    public function nullableDate(string $field): ?string
    {
        $value = $this->required($field);

        return null === $value || self::isDate($value) ? $value : throw $this->wrong($field, 'a date YYYY-MM-DD or null');
    }

    /** A calendar date YYYY-MM-DD. */
    public function date(string $field): string
    {
        $value = $this->required($field);

        return self::isDate($value) ? $value : throw $this->wrong($field, 'a date YYYY-MM-DD');
    }

    /** A time of day HH:MM, 24-hour. */
    public function time(string $field): string
    {
        $value = $this->required($field);

        return \is_string($value) && 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : throw $this->wrong($field, 'a time HH:MM');
    }

    /** Epoch milliseconds. */
    public function timestamp(string $field): int
    {
        $value = $this->required($field);

        return \is_int($value) ? $value : throw $this->wrong($field, 'epoch milliseconds');
    }

    /** Epoch milliseconds; null when the key is missing or null. */
    public function optionalTimestamp(string $field): ?int
    {
        return null === ($this->data[$field] ?? null) ? null : $this->timestamp($field);
    }

    public function nullableInt(string $field): ?int
    {
        $value = $this->required($field);

        return null === $value || \is_int($value) ? $value : throw $this->wrong($field, 'a whole number or null');
    }

    public function number(string $field): float
    {
        $value = $this->required($field);

        return \is_int($value) || \is_float($value) ? (float) $value : throw $this->wrong($field, 'a number');
    }

    public function bool(string $field): bool
    {
        $value = $this->required($field);

        return \is_bool($value) ? $value : throw $this->wrong($field, 'true or false');
    }

    /** Null when the key is missing or null. */
    public function optionalBool(string $field): ?bool
    {
        return null === ($this->data[$field] ?? null) ? null : $this->bool($field);
    }

    /**
     * Null when the key is missing or null.
     *
     * @param list<string> $allowed
     */
    public function optionalOneOf(string $field, array $allowed): ?string
    {
        return null === ($this->data[$field] ?? null) ? null : $this->oneOf($field, $allowed);
    }

    /** @return list<string> */
    public function stringList(string $field): array
    {
        $value = $this->list($field);
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw $this->wrong($field, 'a list of strings');
            }
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public function objectList(string $field): array
    {
        $value = $this->list($field);
        foreach ($value as $item) {
            if (!\is_array($item) || ([] !== $item && array_is_list($item))) {
                throw $this->wrong($field, 'a list of objects');
            }
        }

        return $value;
    }

    /**
     * An object whose values are lists of strings, such as a Comment's Reactions
     * (emoji => Member ids). `{}` arrives as an empty array.
     *
     * @return array<string, list<string>>
     */
    public function stringListMap(string $field): array
    {
        $value = $this->required($field);
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw $this->wrong($field, 'an object of lists of strings');
        }
        foreach ($value as $list) {
            if (!\is_array($list) || !array_is_list($list) || [] !== array_filter($list, static fn (mixed $item): bool => !\is_string($item))) {
                throw $this->wrong($field, 'an object of lists of strings');
            }
        }

        return $value;
    }

    private static function isDate(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** @return list<mixed> */
    private function list(string $field): array
    {
        $value = $this->required($field);

        return \is_array($value) && array_is_list($value) ? $value : throw $this->wrong($field, 'a list');
    }

    private function required(string $field): mixed
    {
        return \array_key_exists($field, $this->data) ? $this->data[$field] : throw SyncRejected::invalid(\sprintf('The %s has no "%s".', $this->record, $field));
    }

    private function wrong(string $field, string $expected): SyncRejected
    {
        return SyncRejected::invalid(\sprintf('The %s\'s "%s" must be %s.', $this->record, $field, $expected));
    }
}
