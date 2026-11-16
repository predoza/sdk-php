<?php

declare(strict_types=1);

namespace Predoza\Laravel;

use Closure;
use Illuminate\Http\Request;

/**
 * Rules for when the logged-in user must play as a guest, beyond your MemberDirectory, e.g. staff impersonating a
 * member:
 *
 *     Predoza::refuseWhen(fn (Request $request): bool => app('impersonate')->isImpersonating());
 */
class Predoza
{
    /** @var list<Closure(Request): bool> */
    private static array $refusals = [];

    /**
     * @param Closure(Request): bool $rule
     */
    public static function refuseWhen(Closure $rule): void
    {
        self::$refusals[] = $rule;
    }

    public static function refuses(Request $request): bool
    {
        foreach (self::$refusals as $rule) {
            if ($rule($request)) {
                return true;
            }
        }

        return false;
    }

    public static function forgetRules(): void
    {
        self::$refusals = [];
    }
}
