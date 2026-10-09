<?php

namespace App\Install\Dto;

final class InstallWizardData
{
    public string $code = '';
    public string $name = '';
    public string $domain = '';
    public string $locale = 'es';
    public string $timezone = 'Europe/Madrid';
    public string $tagline = '';
    public string $primaryColor = '#1a365d';
    public string $secondaryColor = '#2c5282';
    public string $adminName = 'Admin';
    public string $adminEmail = 'admin';
    public string $adminPassword = '';

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'domain' => $this->domain,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'tagline' => $this->tagline,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
            'adminName' => $this->adminName,
            'adminEmail' => $this->adminEmail,
            'adminPassword' => $this->adminPassword,
        ];
    }

    public static function fromArray(array $data): self
    {
        $wizard = new self();
        $wizard->code = (string) ($data['code'] ?? '');
        $wizard->name = (string) ($data['name'] ?? '');
        $wizard->domain = (string) ($data['domain'] ?? '');
        $wizard->locale = (string) ($data['locale'] ?? 'es');
        $wizard->timezone = (string) ($data['timezone'] ?? 'Europe/Madrid');
        $wizard->tagline = (string) ($data['tagline'] ?? '');
        $wizard->primaryColor = (string) ($data['primaryColor'] ?? '#1a365d');
        $wizard->secondaryColor = (string) ($data['secondaryColor'] ?? '#2c5282');
        $wizard->adminName = (string) ($data['adminName'] ?? '');
        $wizard->adminEmail = (string) ($data['adminEmail'] ?? '');
        $wizard->adminPassword = (string) ($data['adminPassword'] ?? '');

        return $wizard;
    }
}
