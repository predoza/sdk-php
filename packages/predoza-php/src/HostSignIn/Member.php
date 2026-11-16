<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

use InvalidArgumentException;

/**
 * A member of your site as the game sees them. Pass the email only once your site has confirmed it: Predoza keys
 * accounts by address, so an unconfirmed one could claim someone else's account.
 */
class Member
{
    private const ID = '/^[A-Za-z0-9_-]{1,64}$/';

    /**
     * @param string $id your site's id for the member: letters, digits, "_" or "-", up to 64 characters
     * @param string|null $nickname the member's public name on your site, never an email address
     * @param string|null $locale the member's language, e.g. "pl" or "pt_BR"
     * @param string|null $confirmedEmail the member's address, only when your site has confirmed it
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $nickname = null,
        public readonly ?string $locale = null,
        public readonly ?string $confirmedEmail = null,
    ) {
        if (!preg_match(self::ID, $id)) {
            throw new InvalidArgumentException('A member id is 1-64 letters, digits, "_" or "-"; map other ids before passing them');
        }
    }
}
