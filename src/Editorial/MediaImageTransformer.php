<?php

namespace App\Editorial;

final class MediaImageTransformer
{
    /** @return list<string> */
    public static function operationLabels(): array
    {
        return [
            'rotate_cw' => 'Rotar 90° derecha',
            'rotate_ccw' => 'Rotar 90° izquierda',
            'flip_h' => 'Voltear horizontal',
            'flip_v' => 'Voltear vertical',
            'grayscale' => 'Escala de grises',
            'anonymize_blur' => 'Anonimizar (desenfoque total)',
            'anonymize_pixelate' => 'Anonimizar (pixelar)',
            'anonymize_center' => 'Anonimizar rostros (centro)',
        ];
    }

    public static function isValidOperation(string $operation): bool
    {
        return isset(self::operationLabels()[$operation]);
    }

    public function transformInPlace(string $absolutePath, string $operation): void
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Archivo de imagen no encontrado.');
        }

        if (!class_exists(\Imagick::class)) {
            throw new \RuntimeException('Imagick no está disponible en el servidor.');
        }

        if (!self::isValidOperation($operation)) {
            throw new \InvalidArgumentException('Operación de imagen no válida.');
        }

        $imagick = new \Imagick($absolutePath);
        $imagick->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

        match ($operation) {
            'rotate_cw' => $imagick->rotateImage('#ffffff', 90),
            'rotate_ccw' => $imagick->rotateImage('#ffffff', -90),
            'flip_h' => $imagick->flopImage(),
            'flip_v' => $imagick->flipImage(),
            'grayscale' => $imagick->modulateImage(100, 0, 100),
            'anonymize_blur' => $this->applyGaussianBlur($imagick, 30),
            'anonymize_pixelate' => $this->applyPixelate($imagick, 16),
            'anonymize_center' => $this->applyCenterBlur($imagick),
            default => throw new \InvalidArgumentException('Operación no soportada.'),
        };

        $imagick->stripImage();
        if (!$imagick->writeImage($absolutePath)) {
            throw new \RuntimeException('No se pudo guardar la imagen transformada.');
        }

        $imagick->clear();
        $imagick->destroy();
    }

    private function applyGaussianBlur(\Imagick $imagick, int $radius): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $imagick->gaussianBlurImage($radius, $radius / 2);
        }
    }

    private function applyPixelate(\Imagick $imagick, int $blockSize): void
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        $smallW = max(1, (int) floor($width / $blockSize));
        $smallH = max(1, (int) floor($height / $blockSize));
        $imagick->resizeImage($smallW, $smallH, \Imagick::FILTER_POINT, 1);
        $imagick->resizeImage($width, $height, \Imagick::FILTER_POINT, 1);
    }

    private function applyCenterBlur(\Imagick $imagick): void
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        $regionW = (int) round($width * 0.55);
        $regionH = (int) round($height * 0.45);
        $x = (int) round(($width - $regionW) / 2);
        $y = (int) round(($height - $regionH) / 3);

        $region = clone $imagick;
        $region->cropImage($regionW, $regionH, $x, $y);
        $this->applyGaussianBlur($region, 22);
        $this->applyPixelate($region, 8);

        $imagick->compositeImage($region, \Imagick::COMPOSITE_OVER, $x, $y);
        $region->clear();
        $region->destroy();
    }
}
