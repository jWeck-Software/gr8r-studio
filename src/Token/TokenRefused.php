<?php

namespace App\Token;

/** A link that does not work: no open link has this token, or it has expired. Callers say why in their own words. */
final class TokenRefused extends \RuntimeException
{
    private function __construct(public readonly bool $expired)
    {
        parent::__construct($expired ? 'The link has expired.' : 'No open link has this token.');
    }

    /** It never existed, was used already, was replaced by a newer one or was revoked. */
    public static function unknown(): self
    {
        return new self(false);
    }

    public static function expired(): self
    {
        return new self(true);
    }
}
