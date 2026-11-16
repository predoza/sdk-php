<?php

declare(strict_types=1);

return [

    // PREDOZA_SITE_KEY from the Host sign-in page of the Predoza client panel
    'site_key' => env('PREDOZA_SITE_KEY'),

    // your class implementing Predoza\HostSignIn\MemberDirectory, e.g. App\Predoza\Members::class
    'members' => null,

    // the auth guard your members sign in with; null for the default guard
    'guard' => null,

    // the handoff route embed.js calls on your own pages; false to register it yourself
    'handoff_path' => '/predoza/handoff',

    // where "Log in" and "Create an account" in the game send the member
    'login_url' => '/login',
    'register_url' => '/register',

];
