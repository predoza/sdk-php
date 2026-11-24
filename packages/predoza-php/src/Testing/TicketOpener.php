<?php

declare(strict_types=1);

namespace Predoza\Testing;

use InvalidArgumentException;
use Predoza\HostSignIn\Base64Url;
use Predoza\HostSignIn\SiteKey;

/**
 * Opens tickets the way Predoza does, for tests only: it needs
 * Predoza's secret key, which in production only Predoza has. Single use is not tracked here.
 */
class TicketOpener
{
    private const FORMAT = '/^v1\.([a-z0-9]{1,16})\.([A-Za-z0-9_-]{100,4096})$/';

    private const MAX_AGE_SECONDS = 90;

    private const MAX_EARLY_SECONDS = 30;

    /** @var array<string, non-empty-string> site key id => public key */
    private array $siteKeys = [];

    /**
     * @param array<string, string> $predozaSecretKeys key id => X25519 secret key
     * @param string[] $sitePublicKeys Ed25519 public keys of the site
     */
    public function __construct(private array $predozaSecretKeys, array $sitePublicKeys)
    {
        foreach ($sitePublicKeys as $publicKey) {
            if ($publicKey === '') {
                throw new InvalidArgumentException('A site public key is 32 bytes');
            }
            $this->siteKeys[SiteKey::idOf($publicKey)] = $publicKey;
        }
    }

    /**
     * @throws TicketRefused
     */
    public function open(string $ticket, string $game, string $verifier, int $now): OpenedTicket
    {
        $opened = $this->unseal($ticket);
        $claims = $opened->claims;

        if (($claims['check'] ?? false) === true) {
            throw new TicketRefused('malformed');
        }
        foreach (['sub' => '/^[A-Za-z0-9_-]{1,64}$/', 'challenge' => '/^[A-Za-z0-9_-]{43}$/', 'game' => '/^[A-Za-z0-9_-]{1,64}$/'] as $key => $pattern) {
            if (!is_string($claims[$key] ?? null) || !preg_match($pattern, $claims[$key])) {
                throw new TicketRefused('malformed');
            }
        }
        if ($claims['game'] !== $game) {
            throw new TicketRefused('wrong_game');
        }
        if (!hash_equals($claims['challenge'], Base64Url::encode(hash('sha256', $verifier, true)))) {
            throw new TicketRefused('verifier_mismatch');
        }
        self::ensureFresh($opened->issuedAt, $now);

        return $opened;
    }

    /**
     * @throws TicketRefused
     */
    public function check(string $ticket, int $now): OpenedTicket
    {
        $opened = $this->unseal($ticket);

        if (($opened->claims['check'] ?? false) !== true) {
            throw new TicketRefused('malformed');
        }
        self::ensureFresh($opened->issuedAt, $now);

        return $opened;
    }

    private function unseal(string $ticket): OpenedTicket
    {
        if (!preg_match(self::FORMAT, $ticket, $parts)) {
            throw new TicketRefused('malformed');
        }
        $secretKey = $this->predozaSecretKeys[$parts[1]] ?? throw new TicketRefused('unknown_predoza_key');
        $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secretKey, sodium_crypto_box_publickey_from_secretkey($secretKey));
        $sealed = Base64Url::decode($parts[2]);
        $opened = $sealed === null ? false : sodium_crypto_box_seal_open($sealed, $keyPair);

        if (!is_string($opened) || strlen($opened) <= SODIUM_CRYPTO_SIGN_BYTES) {
            throw new TicketRefused('not_for_predoza');
        }
        $signature = substr($opened, 0, SODIUM_CRYPTO_SIGN_BYTES);
        $json = substr($opened, SODIUM_CRYPTO_SIGN_BYTES);
        $claims = self::claims($json);
        $issuedAt = $claims['iat'] ?? null;

        if (($claims['v'] ?? null) !== 1 || !is_int($issuedAt)) {
            throw new TicketRefused('malformed');
        }
        foreach (['jti' => '/^[A-Za-z0-9_-]{22}$/', 'sk' => '/^[A-Za-z0-9_-]{16}$/', 'sdk' => '#^[a-z0-9-]{1,16}/[0-9A-Za-z.+-]{1,32}$#'] as $key => $pattern) {
            if (!is_string($claims[$key] ?? null) || !preg_match($pattern, $claims[$key])) {
                throw new TicketRefused('malformed');
            }
        }
        $siteKeyId = (string) $claims['sk'];
        $publicKey = $this->siteKeys[$siteKeyId] ?? throw new TicketRefused('unknown_site_key');

        if (!sodium_crypto_sign_verify_detached($signature, $json, $publicKey)) {
            throw new TicketRefused('bad_signature');
        }

        return new OpenedTicket($claims, $siteKeyId, $issuedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private static function claims(string $json): array
    {
        $decoded = json_decode($json, true, 4);

        if (!is_array($decoded)) {
            throw new TicketRefused('malformed');
        }
        $claims = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new TicketRefused('malformed');
            }
            $claims[$key] = $value;
        }

        return $claims;
    }

    private static function ensureFresh(int $issuedAt, int $now): void
    {
        if ($issuedAt < $now - self::MAX_AGE_SECONDS) {
            throw new TicketRefused('expired');
        }
        if ($issuedAt > $now + self::MAX_EARLY_SECONDS) {
            throw new TicketRefused('not_yet_valid');
        }
    }
}
