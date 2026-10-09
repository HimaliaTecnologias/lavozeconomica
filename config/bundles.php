<?php

return [
    bundles::class => ['Symfony\Bundle\FrameworkBundle\FrameworkBundle' => array (
  'all' => true,
), 'Symfony\Bundle\SecurityBundle\SecurityBundle' => array (
  'all' => true,
), 'Symfony\Bundle\TwigBundle\TwigBundle' => array (
  'all' => true,
), 'Doctrine\Bundle\DoctrineBundle\DoctrineBundle' => array (
  'all' => true,
), 'Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle' => array (
  'all' => true,
)],
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
];
