<?php

declare(strict_types=1);

namespace Predoza\Laravel\View;

use Illuminate\Support\Facades\App;
use Illuminate\View\Component;
use Predoza\Embed\EmbedSnippet;

/**
 * <x-predoza-game feature="sf_CDSofyqv" />: the game's element, with embed.js after the first game of the page. The
 * game follows the app's locale unless lang is given.
 */
class Game extends Component
{
    public function __construct(
        private EmbedSnippet $snippet,
        public string $feature,
        public ?string $lang = null,
        public ?string $title = null,
        public ?string $theme = null,
        public ?string $class = null,
    ) {
    }

    public function render(): string
    {
        return $this->snippet->render($this->feature, $this->lang ?? App::getLocale(), $this->title, $this->theme, $this->class);
    }
}
