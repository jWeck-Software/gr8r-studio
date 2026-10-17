<?php

namespace App\Sync;

use App\Entity\Member;

/**
 * A singleton that is the signed-in Member's own, such as their Preferences: a `set` replaces it for
 * them only, and everyone may change their own, Guests included. The snapshot returns each Member
 * theirs. A subclass names the type and checks the value's shape.
 */
abstract readonly class PersonalSetting implements SingletonHandler
{
    /** The most a setting may take as JSON: a Member's settings are loaded with every bootstrap. */
    public const int MAX_BYTES = 65536;

    final public function set(Member $actor, mixed $data): void
    {
        $value = $this->valid($data);
        if (\strlen(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            throw SyncRejected::invalid(\sprintf('The %s setting may take at most %d KB.', $this->type(), self::MAX_BYTES / 1024));
        }
        $actor->keepPersonal($this->type(), $value);
    }

    /**
     * @return mixed the value to store
     *
     * @throws SyncRejected when the value has the wrong shape
     */
    abstract protected function valid(mixed $data): mixed;

    /**
     * The value as a JSON object (`{}` arrives as an empty array), refused with this message otherwise.
     *
     * @return array<string, mixed>
     */
    protected static function object(mixed $data, string $message): array
    {
        return \is_array($data) && ([] === $data || !array_is_list($data)) ? $data : throw SyncRejected::invalid($message);
    }
}
