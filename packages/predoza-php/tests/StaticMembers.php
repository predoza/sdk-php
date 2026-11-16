<?php

declare(strict_types=1);

namespace Predoza\Tests;

use Predoza\HostSignIn\Member;
use Predoza\HostSignIn\MemberDirectory;

final class StaticMembers implements MemberDirectory
{
    public function memberFor(object $user): ?Member
    {
        if (!$user instanceof SiteUser || $user->banned) {
            return null;
        }

        return new Member($user->id, $user->nickname, 'pl', $user->emailConfirmed ? 'cat@example.com' : null);
    }
}
