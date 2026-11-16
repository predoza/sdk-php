<?php

declare(strict_types=1);

namespace Predoza\Laravel\Tests;

use Illuminate\Auth\GenericUser;
use Predoza\HostSignIn\Member;
use Predoza\HostSignIn\MemberDirectory;

final class Members implements MemberDirectory
{
    public function memberFor(object $user): ?Member
    {
        return $user instanceof GenericUser ? new Member((string) $user->getAuthIdentifier(), (string) $user->name, 'pl') : null;
    }
}
