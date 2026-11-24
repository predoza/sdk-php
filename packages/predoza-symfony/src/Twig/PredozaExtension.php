<?php

declare(strict_types=1);

namespace Predoza\Symfony\Twig;

use Predoza\Embed\EmbedSnippet;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ predoza_game('sf_CDSofyqv') }}: the game's element, with embed.js after the first game of the page. The game
 * follows the request's locale unless "lang" is given.
 */
class PredozaExtension extends AbstractExtension
{
    private const SCRIPT_PRINTED = '_predoza_script_printed';

    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urls,
        private string $scriptUrl,
        private ?string $loginUrl,
        private ?string $registerUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('predoza_game', $this->game(...), ['is_safe' => ['html']])];
    }

    /**
     * @param array{lang?: string, title?: string, theme?: string, class?: string} $options
     */
    public function game(string $game, array $options = []): string
    {
        $request = $this->requestStack->getMainRequest();
        $snippet = new EmbedSnippet($this->urls->generate('predoza_handoff'), $this->url($this->loginUrl), $this->url($this->registerUrl), $this->scriptUrl);
        $html = $snippet->element($game, $options['lang'] ?? $request?->getLocale(), $options['title'] ?? null, $options['theme'] ?? null, $options['class'] ?? null);

        if ($request === null || !$request->attributes->getBoolean(self::SCRIPT_PRINTED)) {
            $request?->attributes->set(self::SCRIPT_PRINTED, true);
            $html .= "\n" . $snippet->script();
        }

        return $html;
    }

    /**
     * A path or URL as it is, anything else as a route name (localized routes follow the request's locale).
     */
    private function url(?string $pathOrRoute): ?string
    {
        if ($pathOrRoute === null || str_starts_with($pathOrRoute, '/') || str_contains($pathOrRoute, '://')) {
            return $pathOrRoute;
        }

        return $this->urls->generate($pathOrRoute);
    }
}
