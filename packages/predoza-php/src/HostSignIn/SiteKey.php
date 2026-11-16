<?php

declare(strict_types=1);

namespace Predoza\HostSignIn;

use LogicException;
use SensitiveParameter;

/**
 * The PREDOZA_SITE_KEY value from your Host sign-in page in the Predoza client panel:
 * "pzsk.{predoza key id}.{Predoza's public key}.{your site's private seed}". It never prints its private half.
 */
class SiteKey
{
    private const PREFIX = 'pzsk';

    private const PREDOZA_KID = '/^[a-z0-9]{1,16}$/';

    public readonly string $predozaKid;

    public readonly string $predozaPublicKey;

    public readonly string $id;

    /** @var non-empty-string */
    private string $secretKey;

    public function __construct(#[SensitiveParameter] string $envValue)
    {
        $parts = explode('.', trim($envValue));

        if (count($parts) !== 4 || $parts[0] !== self::PREFIX || !preg_match(self::PREDOZA_KID, $parts[1])) {
            throw new InvalidSiteKey('PREDOZA_SITE_KEY is not a Predoza site key; copy it again from your Host sign-in page');
        }
        $predozaPublicKey = Base64Url::decode($parts[2]);
        $seed = Base64Url::decode($parts[3]);

        if ($predozaPublicKey === null || strlen($predozaPublicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES || $seed === null || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new InvalidSiteKey('PREDOZA_SITE_KEY is damaged; copy it again from your Host sign-in page');
        }
        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $this->predozaKid = $parts[1];
        $this->predozaPublicKey = $predozaPublicKey;
        $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
        $this->id = self::idOf(sodium_crypto_sign_publickey($keyPair));
    }

    public function sign(string $message): string
    {
        return sodium_crypto_sign_detached($message, $this->secretKey);
    }

    public static function idOf(string $publicKey): string
    {
        return Base64Url::encode(substr(hash('sha256', $publicKey, true), 0, 12));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'predozaKid' => $this->predozaKid, 'secretKey' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('A site key is never serialized');
    }
}
