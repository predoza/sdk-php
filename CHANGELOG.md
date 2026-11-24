# Changelog

## 1.1.0 - 2026-11-24

Predoza now calls every embeddable a game, on the wire too. This release follows it and is a breaking change on
purpose: update your code when you upgrade, and upgrade when Predoza announces the switch, since 1.0.x and the
renamed Predoza do not work together.

- The handoff route reads `{"challenge", "game"}` instead of `{"challenge", "feature"}`; a body with `feature` gets a 400.
- Tickets carry the claim `game` instead of `feature`.
- The embed element is `<div data-predoza-game="…">` instead of `data-predoza-feature`.
- `EmbedSnippet::render()` and `element()`, `TicketSealer::seal()` and `TicketOpener::open()` take `$game` instead
  of `$feature`; `FakeFrame` has `$game` instead of `$feature`; `TicketOpener` refuses another game's ticket with
  `wrong_game` instead of `wrong_feature`.
- Symfony: `predoza_game()` names its first argument `game`.
- Laravel: `<x-predoza-game game="…" />` instead of `feature="…"`.

## 1.0.0 - 2026-11-16

First release.
