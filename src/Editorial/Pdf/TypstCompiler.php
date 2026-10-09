<?php

namespace App\Editorial\Pdf;

use Symfony\Component\Process\Process;

final class TypstCompiler
{
    public function __construct(
        private readonly string $typstBinary,
    ) {}

    public function compile(string $sourcePath, string $outputPath, string $rootDir): void
    {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('No se encontró el documento Typst.');
        }

        $process = new Process([
            $this->typstBinary,
            'compile',
            '--root',
            $rootDir,
            $sourcePath,
            $outputPath,
        ]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput()."\n".$process->getOutput());
            throw new \RuntimeException('Typst no pudo generar el PDF: '.$error);
        }

        if (!is_file($outputPath)) {
            throw new \RuntimeException('Typst no generó el archivo PDF.');
        }
    }

    public function isAvailable(): bool
    {
        $process = new Process([$this->typstBinary, '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }
}
