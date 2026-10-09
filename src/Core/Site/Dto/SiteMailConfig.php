<?php

namespace App\Core\Site\Dto;

final readonly class SiteMailConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $host = 'smtp.gmail.com',
        public int $port = 587,
        public string $encryption = 'tls',
        public string $username = '',
        public string $password = '',
        public string $fromEmail = '',
        public string $fromName = '',
        public string $contactRecipient = '',
        public bool $hasPassword = false,
    ) {}

  /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'fromEmail' => $this->fromEmail,
            'fromName' => $this->fromName,
            'contactRecipient' => $this->contactRecipient,
            'hasPassword' => $this->hasPassword,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            && $this->host !== ''
            && $this->username !== ''
            && $this->password !== '';
    }

    public static function normalizeSmtpPassword(string $password): string
    {
        return preg_replace('/\s+/', '', $password) ?? '';
    }

    public static function isGmailHost(string $host): bool
    {
        $host = strtolower($host);

        return str_contains($host, 'gmail.com') || str_contains($host, 'googlemail.com');
    }

    public static function isGmailAppPassword(string $password): bool
    {
        $normalized = self::normalizeSmtpPassword($password);

        return strlen($normalized) === 16 && ctype_alpha($normalized);
    }

    public function needsGmailAppPassword(): bool
    {
        return self::isGmailHost($this->host)
            && $this->password !== ''
            && !self::isGmailAppPassword($this->password);
    }

    public function buildDsn(): string
    {
        $user = rawurlencode($this->username);
        $pass = rawurlencode($this->password);
        $scheme = $this->encryption === 'ssl' ? 'smtps' : 'smtp';
        $dsn = sprintf('%s://%s:%s@%s:%d', $scheme, $user, $pass, $this->host, $this->port);

        if ($this->encryption === 'tls') {
            return $dsn.'?encryption=tls&auth_mode=login';
        }

        if ($this->encryption === 'ssl') {
            return $dsn.'?auth_mode=login';
        }

        return $dsn;
    }

    public function resolvedFromEmail(string $fallbackDomain): string
    {
        if ($this->fromEmail !== '') {
            return $this->fromEmail;
        }

        if ($this->username !== '' && str_contains($this->username, '@')) {
            return $this->username;
        }

        return 'noreply@'.$fallbackDomain;
    }

    public function resolvedFromName(string $siteName): string
    {
        return $this->fromName !== '' ? $this->fromName : $siteName;
    }
}
