<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

/**
 * Your site's rule for who plays signed in. Return null for anyone who should play as a guest: a disabled or banned
 * account, a shared guest account, or staff impersonating a member.
 */
interface MemberDirectory
{
    public function memberFor(object $user): ?Member;
}
