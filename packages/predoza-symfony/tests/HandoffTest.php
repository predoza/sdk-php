<?php

declare(strict_types=1);

namespace Predoza\Symfony\Tests;

use Predoza\HostSignIn\HostSignIn;
use Predoza\Symfony\Command\DoctorCommand;
use Predoza\Symfony\Controller\HandoffController;
use Predoza\Symfony\Tests\App\TestKernel;
use Predoza\Testing\FakeFrame;
use Predoza\Testing\TestKeys;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class HandoffTest extends WebTestCase
{
    private TestKeys $keys;

    private FakeFrame $frame;

    /** @var callable|null */
    private $exceptionHandler;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    // no debug: older Symfony versions leave their debug error handler behind, which PHPUnit flags
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony 6.4.0 registers an exception handler on boot and never removes it
        while (($handler = self::currentExceptionHandler()) !== null && $handler !== $this->exceptionHandler) {
            restore_exception_handler();
        }
    }

    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    protected function setUp(): void
    {
        $this->exceptionHandler = self::currentExceptionHandler();
        $this->keys = TestKeys::create();
        $this->frame = new FakeFrame();
        TestKernel::$siteKey = $this->keys->envValue;
    }

    public function testALoggedInMemberGetsATicketAndAGuestGetsNothing(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('catenaccio', 'x', ['ROLE_USER']));

        $this->handoff($client);
        self::assertResponseStatusCodeSame(200);
        $ticket = json_decode((string) $client->getResponse()->getContent(), true)['ticket'];
        $claims = $this->keys->opener()->open($ticket, $this->frame->feature, $this->frame->verifier, time())->claims;
        self::assertSame(['48213', 'catenaccio'], [$claims['sub'], $claims['nickname']]);

        static::ensureKernelShutdown();
        $guest = static::createClient();
        $this->handoff($guest);
        self::assertResponseStatusCodeSame(204);
    }

    public function testAnotherSiteIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('catenaccio', 'x', ['ROLE_USER']));

        $client->request('POST', 'https://www.example.com/predoza/handoff', server: $this->server('https://evil.example.net'), content: $this->frame->body());

        self::assertResponseStatusCodeSame(403);
    }

    public function testStaffSwitchedToAMemberNeverHandOverThatMember(): void
    {
        $member = new InMemoryUser('catenaccio', 'x', ['ROLE_USER']);
        $staff = new UsernamePasswordToken(new InMemoryUser('staff', 'x', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);
        $tokens = new TokenStorage();
        $tokens->setToken(new SwitchUserToken($member, 'main', ['ROLE_USER'], $staff));
        $controller = new HandoffController(HostSignIn::create($this->keys->envValue, new App\Members()), $tokens);

        $response = $controller(Request::create('https://www.example.com/predoza/handoff', 'POST', server: $this->server('https://www.example.com'), content: $this->frame->body()));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testTheTwigFunctionPrintsTheScriptOncePerPage(): void
    {
        $client = static::createClient();
        $client->request('GET', 'https://www.example.com/game', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        $html = (string) $client->getResponse()->getContent();

        self::assertSame(1, substr_count($html, '<script'));
        self::assertStringContainsString('data-auth-url="/predoza/handoff" data-login-url="/login" data-register-url="/register"', $html);
        self::assertStringContainsString('<div data-predoza-feature="sf_another1" data-lang="pl"></div>', $html);
    }

    public function testTheDoctorCommandIsRegistered(): void
    {
        self::bootKernel();

        self::assertTrue(self::getContainer()->has(DoctorCommand::class));
    }

    private function handoff(KernelBrowser $client): void
    {
        $client->request('POST', 'https://www.example.com/predoza/handoff', server: $this->server('https://www.example.com'), content: $this->frame->body());
    }

    /**
     * @return array<string, string>
     */
    private function server(string $origin): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $origin, 'HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_X_PREDOZA_HANDOFF' => '1'];
    }
}
