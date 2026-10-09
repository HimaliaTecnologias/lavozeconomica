<?php

namespace App\Editorial;

use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ClassifiedCategory;
use App\Entity\Site\ForumCategory;
use App\Entity\Site\ForumTopic;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class CommunityCategorySeeder
{
    public function prepareLegacyMigration(Connection $conn): void
    {
        $sm = $conn->createSchemaManager();
        if (!$sm->tablesExist(['classified_ads'])) {
            return;
        }

        $classifiedColumns = $sm->listTableColumns('classified_ads');
        if (isset($classifiedColumns['type'])) {
            $this->ensureClassifiedCategoriesTable($conn, $sm);
            $categoryIds = $this->upsertDefaultClassifiedCategories($conn);
            if (!isset($classifiedColumns['category_id'])) {
                $conn->executeStatement('ALTER TABLE classified_ads ADD category_id INT DEFAULT NULL');
            }
            foreach ($categoryIds as $slug => $id) {
                $conn->executeStatement(
                    'UPDATE classified_ads SET category_id = ? WHERE type = ? AND (category_id IS NULL OR category_id = 0)',
                    [$id, $slug],
                );
            }
            $fallbackId = $categoryIds['sale'] ?? array_values($categoryIds)[0];
            $conn->executeStatement(
                'UPDATE classified_ads SET category_id = ? WHERE category_id IS NULL OR category_id = 0',
                [$fallbackId],
            );
        }

        if ($sm->tablesExist(['forum_topics'])) {
            $forumColumns = $sm->listTableColumns('forum_topics');
            if (!isset($forumColumns['category_id'])) {
                $this->ensureForumCategoriesTable($conn, $sm);
                $forumCategoryIds = $this->upsertDefaultForumCategories($conn);
                $conn->executeStatement('ALTER TABLE forum_topics ADD category_id INT DEFAULT NULL');
                $generalId = $forumCategoryIds['general'] ?? array_values($forumCategoryIds)[0];
                $conn->executeStatement(
                    'UPDATE forum_topics SET category_id = ? WHERE category_id IS NULL OR category_id = 0',
                    [$generalId],
                );
            }
        }
    }

    public function ensureDefaults(EntityManagerInterface $em): void
    {
        $this->ensureForumCategories($em);
        $this->ensureClassifiedCategories($em);
        $this->assignMissingForumCategories($em);
        $this->assignMissingClassifiedCategories($em);
        $em->flush();
    }

    private function ensureForumCategories(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(ForumCategory::class);
        if ($repo->count([]) > 0) {
            return;
        }

        foreach ($this->defaultForumCategories() as [$name, $slug, $sortOrder, $description]) {
            $category = new ForumCategory($name, $slug);
            $category->setSortOrder($sortOrder);
            $category->setDescription($description);
            $em->persist($category);
        }
    }

    private function ensureClassifiedCategories(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(ClassifiedCategory::class);
        if ($repo->count([]) > 0) {
            return;
        }

        foreach ($this->defaultClassifiedCategories() as [$name, $slug, $sortOrder]) {
            $category = new ClassifiedCategory($name, $slug);
            $category->setSortOrder($sortOrder);
            $em->persist($category);
        }
    }

    private function assignMissingForumCategories(EntityManagerInterface $em): void
    {
        $default = $em->getRepository(ForumCategory::class)->findOneBy(['slug' => 'general']);
        if ($default === null) {
            return;
        }

        $topics = $em->createQueryBuilder()
            ->select('t')
            ->from(ForumTopic::class, 't')
            ->andWhere('t.category IS NULL')
            ->getQuery()
            ->getResult();

        foreach ($topics as $topic) {
            $topic->setCategory($default);
        }
    }

    private function assignMissingClassifiedCategories(EntityManagerInterface $em): void
    {
        $categories = $em->getRepository(ClassifiedCategory::class)->findAll();
        if ($categories === []) {
            return;
        }

        $default = $categories[0];
        foreach ($categories as $category) {
            if ($category->getSlug() === 'sale') {
                $default = $category;
                break;
            }
        }

        $ads = $em->createQueryBuilder()
            ->select('a')
            ->from(ClassifiedAd::class, 'a')
            ->andWhere('a.category IS NULL')
            ->getQuery()
            ->getResult();

        foreach ($ads as $ad) {
            $ad->setCategory($default);
        }
    }

    private function ensureClassifiedCategoriesTable(Connection $conn, \Doctrine\DBAL\Schema\AbstractSchemaManager $sm): void
    {
        if ($sm->tablesExist(['classified_categories'])) {
            return;
        }

        $conn->executeStatement('CREATE TABLE classified_categories (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            UNIQUE INDEX uniq_classified_category_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    private function ensureForumCategoriesTable(Connection $conn, \Doctrine\DBAL\Schema\AbstractSchemaManager $sm): void
    {
        if ($sm->tablesExist(['forum_categories'])) {
            return;
        }

        $conn->executeStatement('CREATE TABLE forum_categories (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            UNIQUE INDEX uniq_forum_category_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    /** @return array<string, int> */
    private function upsertDefaultClassifiedCategories(Connection $conn): array
    {
        $ids = [];
        foreach ($this->defaultClassifiedCategories() as [$name, $slug, $sortOrder]) {
            $existing = $conn->fetchOne('SELECT id FROM classified_categories WHERE slug = ?', [$slug]);
            if ($existing !== false && $existing !== null) {
                $ids[$slug] = (int) $existing;
                continue;
            }
            $conn->executeStatement(
                'INSERT INTO classified_categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, 1)',
                [$name, $slug, $sortOrder],
            );
            $ids[$slug] = (int) $conn->lastInsertId();
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function upsertDefaultForumCategories(Connection $conn): array
    {
        $ids = [];
        foreach ($this->defaultForumCategories() as [$name, $slug, $sortOrder, $description]) {
            $existing = $conn->fetchOne('SELECT id FROM forum_categories WHERE slug = ?', [$slug]);
            if ($existing !== false && $existing !== null) {
                $ids[$slug] = (int) $existing;
                continue;
            }
            $conn->executeStatement(
                'INSERT INTO forum_categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, 1)',
                [$name, $slug, $description, $sortOrder],
            );
            $ids[$slug] = (int) $conn->lastInsertId();
        }

        return $ids;
    }

    /** @return list<array{0: string, 1: string, 2: int, 3: string}> */
    private function defaultForumCategories(): array
    {
        return [
            ['General', 'general', 0, 'Debate abierto sobre cualquier tema de interés para la comunidad.'],
            ['Actualidad', 'actualidad', 10, 'Temas sobre la actualidad local, nacional e internacional.'],
            ['Opinión', 'opinion', 20, 'Comparte tu punto de vista y debate ideas con otros lectores.'],
            ['Temas locales', 'local', 30, 'Asuntos del municipio, barrio y comarca.'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    private function defaultClassifiedCategories(): array
    {
        return [
            ['Venta', 'sale', 0],
            ['Compra / busco', 'wanted', 10],
            ['Aviso', 'notice', 20],
            ['Servicios', 'services', 30],
        ];
    }
}
