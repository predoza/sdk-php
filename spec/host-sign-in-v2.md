# Host sign-in, protocol v2

A client's site signs its logged-in members in to a Predoza game without a second login. The site hands the game a
**ticket**: what it knows about the member, signed with the site's key and sealed to Predoza's key. Predoza opens and
checks it itself; nothing calls the client's site. The SDKs (`predoza/predoza-php` and the framework packages)
implement the site's side; [`host-sign-in-v2-vectors.json`](host-sign-in-v2-vectors.json) holds test vectors that Predoza and every SDK run.

The words MUST, MUST NOT and SHOULD are used as in RFC 2119.

## Keys

- **Predoza key**: an X25519 key pair (libsodium `crypto_box`), named by a key id (`[a-z0-9]{1,16}`). Predoza holds
  the secret halves in `PREDOZA_HOST_KEYS` (`kid:base64url secret key`, comma separated, the first is current).
- **Site key**: an Ed25519 key pair (libsodium `crypto_sign`). The client creates it on its Host sign-in page in the
  client panel; Predoza generates it, shows the private half once and keeps only the public half. Its id is
  `base64url(sha256(public key)[0..12])`, 16 characters.
- The client copies one value into its server's environment:

  ```
  PREDOZA_SITE_KEY=pzsk.{predoza kid}.{base64url Predoza public key}.{base64url 32-byte Ed25519 seed}
  ```

- A new site key replaces the old one. The old key MUST keep working until the first ticket signed with the new key
  is accepted, and at most 24 hours.
- base64url is RFC 4648 §5 without padding, everywhere.

## Flow

1. The game frame makes a PKCE pair (S256) and posts only the challenge to embed.js on the client's page.
2. embed.js sends `POST {data-auth-url}` on the page's own origin, with the page's cookies:

   ```
   Content-Type: application/json
   X-Predoza-Handoff: 1

   {"challenge": "<43 base64url>", "feature": "<feature public id>"}
   ```

3. The site answers `200 {"ticket": "…"}` for a logged-in member or `204` for a guest, and builds the ticket as below.
4. embed.js passes the ticket to the frame, which sends it with its verifier to Predoza:
   `POST https://play.predoza.com/v1/features/{feature}/auth/handoff {"ticket", "code_verifier"}`.
5. Predoza opens and checks the ticket and answers the frame with a game token (30 idle minutes).

## The site's handoff endpoint

The site MUST refuse, in this order:

| Request | Answer |
|---|---|
| Not `POST` | 405 |
| `X-Predoza-Handoff` is not exactly `1` | 403 |
| `Origin` is not the page's own origin (scheme, host, port) | 403 |
| `Sec-Fetch-Site` is present and not `same-origin` | 403 |
| Media type is not `application/json` (parameters allowed) | 415 |
| Body is not a JSON object with `challenge` matching `^[A-Za-z0-9_-]{43}$` and `feature` matching `^[A-Za-z0-9_-]{1,64}$` | 400 |
| No eligible member: a guest, a disabled or banned account, a shared guest account, staff impersonating a member | 204 |

Every answer MUST carry `Cache-Control: no-store` and MUST NOT carry CORS headers. The endpoint MUST NOT check a CSRF
token (embed.js sends none; the header, Origin and fetch-site checks replace it) and SHOULD NOT start a session.

## The ticket

```
ticket = "v2." kid "." base64url( crypto_box_seal( signature || claims, predoza_public_key ) )
signature = crypto_sign_detached( claims, site_secret_key )      ; 64 bytes
claims = UTF-8 JSON object
```

Sign first, then seal: the signature is hidden too, and Predoza verifies exactly the bytes the site signed.

| Claim | Type | Rule |
|---|---|---|
| `v` | integer | `2` |
| `jti` | string | 16 random bytes, base64url (22 characters); unique per ticket |
| `sk` | string | the site key id |
| `sub` | string | the member's id on the site, `^[A-Za-z0-9_-]{1,64}$` |
| `nickname` | string or null | the member's name on the site; Predoza keeps it when it is 1–32 letters, marks, digits, spaces or `._-` after NFC, starting with a letter or digit, else a placeholder |
| `locale` | string or null | the member's language, e.g. `pl`, `pt_BR`; matched to the game's languages |
| `email` | string or null | MUST be null unless the site has confirmed the address: Predoza keys accounts by address |
| `challenge` | string | the challenge from the request |
| `feature` | string | the feature from the request |
| `iat` | integer | unix seconds when the ticket was made |
| `sdk` | string | `{language}/{version}`, e.g. `php/1.0.0`, `^[a-z0-9-]{1,16}/[0-9A-Za-z.+-]{1,32}$` |

## Predoza's checks

Predoza refuses a ticket with a reason, in this order. The game only sees `host_code_invalid`; the client sees the
reason on its Host sign-in page.

| Reason | When |
|---|---|
| `malformed` | not `v2.{kid}.{100–4096 base64url}`, claims not as above, or a check ticket |
| `unknown_predoza_key` | the kid is not in `PREDOZA_HOST_KEYS` |
| `not_for_predoza` | the box doesn't open (sealed to another key, or changed) |
| `unknown_site_key` | `sk` is no active key of the feature's client |
| `bad_signature` | the signature doesn't verify with that key |
| `wrong_feature` | `feature` is not the feature signing in |
| `verifier_mismatch` | `base64url(sha256(code_verifier))` is not `challenge` |
| `expired` | `iat` is more than 90 s ago |
| `not_yet_valid` | `iat` is more than 30 s ahead (a ticket lives 60 s; 30 s more either way for clocks) |
| `replayed` | the `jti` was accepted in the last 120 s; checked last, so a refused request never burns a good ticket |

A key of one client never signs for another client's game: the site key is looked up among the keys of the
feature's client only.

## Setup check

`POST https://play.predoza.com/v1/host-sign-in/check {"ticket": "…"}`, from the site's server (the SDK's doctor).
The ticket is built the same way with the claims `v`, `jti`, `sk`, `iat`, `sdk` and `"check": true`, and no member.
Predoza answers `200 {"ok": true, "client": "<slug>", "site_key": "<id>", "current": true}` or
`422 {"ok": false, "reason": "<reason>"}`. It signs nobody in. 20 a minute per IP.

## Test vectors

[`host-sign-in-v2-vectors.json`](host-sign-in-v2-vectors.json) fixes a Predoza key, a site key, a feature, a PKCE pair and a time (`now`):

- `open`: tickets and the expected claims or refusal reason when opened at `now`;
- `check`: setup-check tickets and the expected answer;
- `replay`: one ticket opened twice;
- `handoff`: requests to a site's handoff endpoint (page origin `page_origin`) and the expected status.

Sealed boxes are randomised, so a sealer can't reproduce the tickets byte for byte. An SDK tests its sealer by
opening its own output with the vectors' keys. A change to anything here is protocol v3, never an edit of v2.
