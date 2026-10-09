<?php

namespace App\Install;

use App\Entity\Platform\Site;
use App\Entity\Platform\SiteDomain;
use App\Install\Dto\InstallWizardData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstallWizardValidator
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    /** @return array<string, string> */
    public function validatePeriodico(InstallWizardData $data): array
    {
        $violations = $this->validator->validate($data->toArray(), new Assert\Collection([
            'code' => [
                new Assert\NotBlank(message: 'El código es obligatorio.'),
                new Assert\Regex(
                    pattern: '/^[a-z0-9][a-z0-9_-]{2,31}$/',
                    message: 'Usa minúsculas, números, guiones (3-32 caracteres).',
                ),
            ],
            'name' => [
                new Assert\NotBlank(message: 'El nombre del periódico es obligatorio.'),
                new Assert\Length(max: 255),
            ],
            'domain' => [
                new Assert\NotBlank(message: 'El dominio es obligatorio.'),
                new Assert\Regex(
                    pattern: '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*|localhost|127\.0\.0\.1)$/i',
                    message: 'Dominio no válido (ej: miperiodico.com o localhost).',
                ),
            ],
            'locale' => [new Assert\NotBlank()],
            'timezone' => [new Assert\NotBlank()],
        ], allowExtraFields: true));

        $errors = $this->mapViolations($violations);

        if ($data->code !== '' && $this->platformEm->getRepository(Site::class)->findOneBy(['code' => $data->code])) {
            $errors['code'] = 'Este código ya está en uso.';
        }

        if ($data->domain !== '' && $this->platformEm->getRepository(SiteDomain::class)->findOneBy(['domain' => $data->domain])) {
            $errors['domain'] = 'Este dominio ya está registrado. Usa otro o elimina el periódico existente.';
        }

        return $errors;
    }

    /** @return array<string, string> */
    public function validateApariencia(InstallWizardData $data): array
    {
        $violations = $this->validator->validate($data->toArray(), new Assert\Collection([
            'tagline' => [new Assert\Length(max: 500)],
            'primaryColor' => [
                new Assert\NotBlank(),
                new Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Color hexadecimal inválido.'),
            ],
            'secondaryColor' => [
                new Assert\NotBlank(),
                new Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Color hexadecimal inválido.'),
            ],
        ], allowExtraFields: true));

        return $this->mapViolations($violations);
    }

    /** @return array<string, string> */
    public function validateAdmin(InstallWizardData $data): array
    {
        $violations = $this->validator->validate($data->toArray(), new Assert\Collection([
            'adminName' => [
                new Assert\NotBlank(message: 'El nombre es obligatorio.'),
                new Assert\Length(max: 255),
            ],
            'adminEmail' => [
                new Assert\NotBlank(message: 'El usuario es obligatorio.'),
                new Assert\Length(min: 3, max: 255, minMessage: 'Mínimo 3 caracteres.'),
                new Assert\Regex(
                    pattern: '/^[a-zA-Z0-9._-]+$/',
                    message: 'Solo letras, números, puntos, guiones y guiones bajos.',
                ),
            ],
            'adminPassword' => [
                new Assert\NotBlank(message: 'La contraseña es obligatoria.'),
                new Assert\Length(min: 4, minMessage: 'Mínimo 4 caracteres.'),
            ],
        ], allowExtraFields: true));

        return $this->mapViolations($violations);
    }

    private function mapViolations(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = (string) $violation->getPropertyPath();
            $field = trim($field, '[]');
            if ($field === '') {
                $field = '_form';
            }
            $errors[$field] = $violation->getMessage();
        }

        return $errors;
    }
}
