<?php

declare(strict_types=1);

namespace Predoza\Testing;

use Predoza\HostSignIn\Base64Url;

/**
 * A throwaway Predoza key and site key for your own tests: use envValue as PREDOZA_SITE_KEY and opener() to read the
 * tickets your handoff route answers.
 */
class TestKeys
{
    private const KID = 'test';

    public function __construct(
        public readonly string $envValue,
        private string $predozaSecretKey,
        private string $sitePublicKey,
    ) {
    }

    public static function create(): self
    {
        $predozaSecretKey = random_bytes(SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $envValue = implode('.', ['pzsk', self::KID, Base64Url::encode(sodium_crypto_box_publickey_from_secretkey($predozaSecretKey)), Base64Url::encode($seed)]);

        return new self($envValue, $predozaSecretKey, sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed)));
    }

    public function opener(): TicketOpener
    {
        return new TicketOpener([self::KID => $this->predozaSecretKey], [$this->sitePublicKey]);
    }
}
