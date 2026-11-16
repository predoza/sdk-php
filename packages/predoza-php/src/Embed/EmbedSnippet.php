<?php

declare(strict_types=1);

namespace Predoza\Embed;

/**
 * The HTML for a Predoza game on your page: the game's element and embed.js, which must load once per page. One
 * instance per page render; render() prints the script with the first game only.
 */
class EmbedSnippet
{
    public const SCRIPT_URL = 'https://play.predoza.com/embed/v1.js';

    private bool $scriptPrinted = false;

    public function __construct(
        private ?string $handoffUrl = '/predoza/handoff',
        private ?string $loginUrl = null,
        private ?string $registerUrl = null,
        private string $scriptUrl = self::SCRIPT_URL,
    ) {
    }

    /**
     * @param string|null $lang your page's language when your site has its own language switcher, e.g. "pl"
     * @param string|null $theme "light", "dark" or "auto"
     */
    public function render(string $feature, ?string $lang = null, ?string $title = null, ?string $theme = null): string
    {
        $html = $this->element($feature, $lang, $title, $theme);

        if (!$this->scriptPrinted) {
            $this->scriptPrinted = true;
            $html .= "\n" . $this->script();
        }

        return $html;
    }

    public function element(string $feature, ?string $lang = null, ?string $title = null, ?string $theme = null): string
    {
        return '<div' . self::attributes([
            'data-predoza-feature' => $feature,
            'data-lang' => $lang,
            'data-title' => $title,
            'data-theme' => $theme,
        ]) . '></div>';
    }

    public function script(): string
    {
        return '<script async' . self::attributes([
            'src' => $this->scriptUrl,
            'data-auth-url' => $this->handoffUrl,
            'data-login-url' => $this->loginUrl,
            'data-register-url' => $this->registerUrl,
        ]) . '></script>';
    }

    /**
     * @param array<string, string|null> $attributes
     */
    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value !== null && $value !== '') {
                $html .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        return $html;
    }
}
