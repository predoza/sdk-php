<?php

declare(strict_types=1);

namespace Predoza\Symfony\Tests\App;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class GameController
{
    public function __construct(private Environment $twig)
    {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render('game.html.twig'));
    }
}
