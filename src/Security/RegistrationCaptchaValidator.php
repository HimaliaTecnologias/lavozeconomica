<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RegistrationCaptchaValidator
{
    private const SESSION_ANSWER_PREFIX = 'form_captcha_answer.';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'RECAPTCHA_SECRET_KEY')]
        private readonly ?string $recaptchaSecretKey = null,
        #[Autowire(env: 'RECAPTCHA_SITE_KEY')]
        private readonly ?string $recaptchaSiteKey = null,
    ) {}

    public function usesRecaptcha(): bool
    {
        return trim((string) $this->recaptchaSecretKey) !== '' && trim((string) $this->recaptchaSiteKey) !== '';
    }

    public function getRecaptchaSiteKey(): string
    {
        return trim((string) $this->recaptchaSiteKey);
    }

    /** @return array{question: string}|null */
    public function issueMathChallenge(string $scope = 'register'): ?array
    {
        if ($this->usesRecaptcha()) {
            return null;
        }

        $a = random_int(2, 12);
        $b = random_int(2, 12);
        $session = $this->requestStack->getSession();
        $session->set($this->sessionKey($scope), $a + $b);

        return ['question' => sprintf('%d + %d', $a, $b)];
    }

    public function validate(Request $request, string $scope = 'register'): bool
    {
        if ($this->isHoneypotTriggered($request)) {
            return false;
        }

        if ($this->usesRecaptcha()) {
            return $this->validateRecaptcha((string) $request->request->get('g-recaptcha-response', ''));
        }

        $answer = trim((string) $request->request->get('captcha_answer', ''));
        if ($answer === '' || !ctype_digit($answer)) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $expected = $session->get($this->sessionKey($scope));
        if (!is_int($expected)) {
            // Backward compatible with older register_captcha_answer key.
            if ($scope === 'register') {
                $expected = $session->get('register_captcha_answer');
            }
            if (!is_int($expected)) {
                return false;
            }
        }

        $valid = (int) $answer === $expected;
        if ($valid) {
            $session->remove($this->sessionKey($scope));
            if ($scope === 'register') {
                $session->remove('register_captcha_answer');
            }
        }

        return $valid;
    }

    private function sessionKey(string $scope): string
    {
        return self::SESSION_ANSWER_PREFIX.$scope;
    }

    private function isHoneypotTriggered(Request $request): bool
    {
        return trim((string) $request->request->get('website', '')) !== '';
    }

    private function validateRecaptcha(string $response): bool
    {
        if ($response === '') {
            return false;
        }

        try {
            $payload = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => trim((string) $this->recaptchaSecretKey),
                    'response' => $response,
                ],
                'timeout' => 10,
            ])->toArray();

            return (bool) ($payload['success'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }
}
