<?php

declare(strict_types=1);

namespace Predoza\Laravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Predoza\Laravel\Predoza;
use Predoza\Testing\FakeFrame;

final class HandoffTest extends TestCase
{
    private FakeFrame $frame;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frame = new FakeFrame();
    }

    public function testALoggedInMemberGetsATicketAndAGuestGetsNothing(): void
    {
        $this->actingAs(new GenericUser(['id' => 48213, 'name' => 'catenaccio']));

        $ticket = $this->handoff()->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('ticket');
        $claims = $this->keys->opener()->open($ticket, $this->frame->feature, $this->frame->verifier, time())->claims;
        $this->assertSame(['48213', 'catenaccio'], [$claims['sub'], $claims['nickname']]);

        $this->app['auth']->forgetGuards();
        $this->handoff()->assertNoContent();
    }

    public function testARefusalRuleMakesTheUserAGuest(): void
    {
        $this->actingAs(new GenericUser(['id' => 48213, 'name' => 'catenaccio']));
        Predoza::refuseWhen(fn (Request $request): bool => true);

        $this->handoff()->assertNoContent();
    }

    public function testAnotherSiteIsRefused(): void
    {
        $this->actingAs(new GenericUser(['id' => 48213, 'name' => 'catenaccio']));

        $this->handoff('https://evil.example.net')->assertForbidden();
    }

    public function testTheRouteSkipsTheCsrfCheck(): void
    {
        $route = Route::getRoutes()->getByName('predoza.handoff');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
    }

    public function testTheComponentPrintsTheScriptOncePerPage(): void
    {
        $html = Blade::render('<x-predoza-game feature="sf_CDSofyqv" /> <x-predoza-game feature="sf_another1" lang="pl" />');

        $this->assertSame(1, substr_count($html, '<script'));
        $this->assertStringContainsString('data-auth-url="/predoza/handoff" data-login-url="/login" data-register-url="/register"', $html);
        $this->assertStringContainsString('<div data-predoza-feature="sf_another1" data-lang="pl"></div>', $html);
    }

    public function testAttributeValuesAreNeverRunAsBlade(): void
    {
        $html = Blade::render('<x-predoza-game feature="sf_x" :title="$title" />', ['title' => '{{ 7 * 7 }}']);

        $this->assertStringContainsString('data-title="{{ 7 * 7 }}"', $html);
    }

    public function testTheDoctorCommandIsRegistered(): void
    {
        $this->assertArrayHasKey('predoza:doctor', $this->app->make(Kernel::class)->all());
    }

    private function handoff(string $origin = 'https://www.example.com'): TestResponse
    {
        return $this->call('POST', 'https://www.example.com/predoza/handoff', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => $origin,
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
            'HTTP_X_PREDOZA_HANDOFF' => '1',
        ], content: $this->frame->body());
    }
}
