<?php

declare(strict_types=1);

namespace Predoza\Symfony\Tests\App;

use Predoza\HostSignIn\Member;
use Predoza\HostSignIn\MemberDirectory;
use Symfony\Component\Security\Core\User\UserInterface;

final class Members implements MemberDirectory
{
    public function memberFor(object $user): ?Member
    {
        return $user instanceof UserInterface ? new Member('48213', $user->getUserIdentifier(), 'pl') : null;
    }
}
