<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DemoContentSeeder
{
    /**
     * @return array{created: int, categories: int, repaired: int}
     */
    public function seed(SiteContext $context, EntityManagerInterface $em): array
    {
        $categories = $this->seedCategories($em);
        $repaired = $this->repairWelcomeArticle($em);
        $created = 0;

        foreach ($this->articleDefinitions() as $definition) {
            if ($em->getRepository(Article::class)->findOneBy(['slug' => $definition['slug']]) !== null) {
                continue;
            }

            $category = $categories[$definition['categorySlug']] ?? null;

            $article = new Article(
                $definition['title'],
                $definition['bodyHtml'],
                $definition['slug'],
                ArticleStatus::Published,
            );
            $article->setLead($definition['lead']);
            if ($definition['subtitle'] !== null) {
                $article->setSubtitle($definition['subtitle']);
            }
            if ($category !== null) {
                $article->setCategory($category);
            }
            $article->publish();
            $em->persist($article);

            foreach ($definition['images'] as $index => $image) {
                $articleImage = new ArticleImage($article, $image['url'], $index);
                $articleImage->setAlt($image['alt'] ?? $definition['title']);
                if (isset($image['caption'])) {
                    $articleImage->setCaption($image['caption']);
                }
                $article->addImage($articleImage);
                $em->persist($articleImage);
            }

            ++$created;
        }

        if ($created > 0 || $repaired) {
            $em->flush();
        }

        return [
            'created' => $created,
            'categories' => count($categories),
            'repaired' => $repaired,
        ];
    }

    /** @return array<string, Category> */
    private function seedCategories(EntityManagerInterface $em): array
    {
        $map = [];
        foreach ($this->categoryDefinitions() as $slug => $name) {
            $existing = $em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
            if ($existing !== null) {
                $map[$slug] = $existing;
                continue;
            }

            $category = new Category($name, $slug);
            $em->persist($category);
            $map[$slug] = $category;
        }

        $em->flush();

        return $map;
    }

    private function repairWelcomeArticle(EntityManagerInterface $em): int
    {
        $article = $em->getRepository(Article::class)->findOneBy(['slug' => 'bienvenida-mymediacms']);
        if ($article === null) {
            return 0;
        }

        $needsRepair = trim($article->getBodyHtml()) === ''
            || $article->getPublishedAt() === null
            || $article->getCreatedAt()->format('Y') === '0000';

        if (!$needsRepair) {
            return 0;
        }

        $article->setBodyHtml(
            '<p>Tu periódico digital con ingesta HimtecMedia, galerías de imágenes y diseño editorial.</p>'
            .'<p>Gestiona el contenido en <strong>Admin → Noticias</strong>.</p>',
        );
        $article->setLead('Plataforma lista para publicar noticias con aspecto de gran periódico digital.');
        $article->setStatus(ArticleStatus::Published);
        $article->publish();

        return 1;
    }

    /** @return array<string, string> */
    private function categoryDefinitions(): array
    {
        return [
            'politica' => 'Política',
            'economia' => 'Economía',
            'sociedad' => 'Sociedad',
            'deportes' => 'Deportes',
            'cultura' => 'Cultura',
        ];
    }

    /** @return list<array{slug: string, title: string, subtitle: ?string, lead: string, bodyHtml: string, categorySlug: string, images: list<array{url: string, alt?: string, caption?: string}>}> */
    private function articleDefinitions(): array
    {
        return [
            [
                'slug' => 'gobierno-anuncia-paquete-urgente-para-la-economia',
                'title' => 'El Gobierno anuncia un paquete urgente de medidas para reactivar la economía',
                'subtitle' => 'Inversión pública, alivio fiscal para pymes y reforma del mercado laboral',
                'lead' => 'El Ejecutivo presenta un plan de choque con 12.000 millones de euros para sostener el empleo y acelerar la transición energética en los próximos dos años.',
                'bodyHtml' => '<p>El Consejo de Ministros ha aprobado este martes un paquete de medidas económicas que combina incentivos a la inversión, ayudas directas a sectores estratégicos y una revisión de trámites para agilizar licencias industriales.</p><p>Según fuentes del ministerio, el objetivo es «proteger el empleo sin frenar la modernización del país». Los detalles se debatirán en las Cortes la próxima semana.</p><p>Los analistas consultados señalan que el plan podría reducir la presión sobre las familias en el corto plazo, aunque advierten de la necesidad de consenso político para su implementación.</p>',
                'categorySlug' => 'politica',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=1200&q=80', 'caption' => 'Sede del ministerio durante la rueda de prensa.'],
                    ['url' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=800&q=80', 'caption' => 'Documentación del plan económico.'],
                ],
            ],
            [
                'slug' => 'mercados-europeos-cerran-en-verde-tras-datos-empleo',
                'title' => 'Los mercados europeos cierran en verde tras los datos de empleo en la eurozona',
                'subtitle' => 'El Ibex 35 sube el 1,2 % y el sector bancario lidera las ganancias',
                'lead' => 'La creación de empleo supera las expectativas en Alemania y Francia, lo que alivia las tensiones sobre una desaceleración más brusca de la actividad.',
                'bodyHtml' => '<p>La sesión bursátil europea cerró con avances generalizados tras conocerse un informe de empleo mejor de lo previsto. El sector financiero fue el más beneficiado por la perspectiva de estabilidad macroeconómica.</p><p>En el mercado de deuda, la rentabilidad de los bonos soberanos cayó ligeramente, reflejando un menor apetito por activos de refugio.</p>',
                'categorySlug' => 'economia',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1611974789855-9c498a259f3d?w=1200&q=80', 'caption' => 'Panel de cotizaciones en una sala de mercados.'],
                ],
            ],
            [
                'slug' => 'ciudades-piloto-energia-solar-comunitaria',
                'title' => 'Diez ciudades españolas se convierten en piloto de energía solar comunitaria',
                'subtitle' => 'El proyecto permitirá instalar placas en edificios públicos y compartir el excedente',
                'lead' => 'Un consorcio de ayuntamientos y cooperativas energéticas arranca un plan que podría abaratar la factura de miles de hogares en zonas urbanas.',
                'bodyHtml' => '<p>La iniciativa, financiada con fondos europeos, prioriza barrios con mayor vulnerabilidad energética. Los primeros paneles se instalarán antes de final de año.</p><p>Los técnicos calculan que cada participante podría ahorrar entre el 15 y el 25 % en electricidad durante los primeros tres años del programa.</p>',
                'categorySlug' => 'sociedad',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=1200&q=80', 'caption' => 'Instalación solar en cubierta municipal.'],
                    ['url' => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800&q=80', 'caption' => 'Paneles en una zona residencial.'],
                ],
            ],
            [
                'slug' => 'seleccion-prepara-duelo-clave-campeonato',
                'title' => 'La selección prepara el duelo clave del campeonato con dos bajas confirmadas',
                'subtitle' => 'El entrenador confía en la cantera para cubrir las ausencias en defensa',
                'lead' => 'El combinado nacional entrenó a puerta cerrada antes del encuentro que puede decidir el liderato de la fase regular.',
                'bodyHtml' => '<p>El técnico evitó confirmar el once inicial, pero admitió que «el equipo está mentalizado para un partido exigente». Miles de aficionados ya han agotado entradas para el estadio.</p><p>En rueda de prensa, destacó la madurez de los jugadores más jóvenes, que han ganado minutos en las últimas semanas.</p>',
                'categorySlug' => 'deportes',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=1200&q=80', 'caption' => 'Entrenamiento de la selección nacional.'],
                ],
            ],
            [
                'slug' => 'festival-cine-inaugura-temporada-premios',
                'title' => 'El festival de cine inaugura su temporada con una mirada a los premios internacionales',
                'subtitle' => 'Películas independientes y documentales copan la programación de la primera semana',
                'lead' => 'La alfombra roja reunió a directores emergentes y estrellas del cine europeo en una gala marcada por el debate sobre la diversidad en la industria.',
                'bodyHtml' => '<p>El evento, que se prolongará durante diez días, incluye secciones dedicadas al cine de autor y a producciones debutantes. La organización espera superar el récord de asistencia del año pasado.</p><p>Entre los títulos más esperados figura un drama ambientado en la crisis migratoria que ya ha cosechado críticas favorables en otros certámenes.</p>',
                'categorySlug' => 'cultura',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=1200&q=80', 'caption' => 'Gala inaugural del festival.'],
                    ['url' => 'https://images.unsplash.com/photo-1517602308752-9f698f40d375?w=800&q=80', 'caption' => 'Proyección en la sala principal.'],
                ],
            ],
            [
                'slug' => 'ayuntamiento-movilidad-sostenible-centro',
                'title' => 'El ayuntamiento acelera la movilidad sostenible con nuevas ciclovías en el centro',
                'subtitle' => 'Se reducirá el tráfico en tres ejes principales y se ampliará el servicio de bicis públicas',
                'lead' => 'La reforma urbanística busca reducir emisiones y recuperar espacio peatonal en el distrito financiero y zonas comerciales.',
                'bodyHtml' => '<p>El plan contempla la instalación de 40 kilómetros de carril bici y la renovación de semaforización inteligente. Los comercios del área participaron en el diseño para minimizar impactos en la actividad económica.</p>',
                'categorySlug' => 'sociedad',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?w=1200&q=80', 'caption' => 'Nueva ciclovía en el centro urbano.'],
                ],
            ],
            [
                'slug' => 'tribunal-abre-vista-caso-corrupcion',
                'title' => 'El tribunal abre la vista oral en un caso de corrupción que afecta a contratos públicos',
                'subtitle' => 'Se investigan adjudicaciones por valor de 80 millones de euros en obras infraestructurales',
                'lead' => 'La Fiscalía sostiene que existió una red de empresas intermediarias para inflar licitaciones. La defensa niega irregularidades y pide la absolución de los acusados.',
                'bodyHtml' => '<p>El juicio, previsto para varias semanas, incluye la declaración de altos cargos administrativos y representantes del sector constructor. La sentencia podría tener implicaciones políticas a nivel regional.</p>',
                'categorySlug' => 'politica',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=1200&q=80', 'caption' => 'Exterior del tribunal en el primer día de sesiones.'],
                ],
            ],
            [
                'slug' => 'startup-tecnologica-duplica-plantilla',
                'title' => 'Una startup tecnológica duplica su plantilla tras cerrar una ronda de 45 millones',
                'subtitle' => 'La empresa especializada en ciberseguridad abrirá un centro de I+D en España',
                'lead' => 'Los fondos permitirán contratar 300 perfiles en ingeniería y datos, con especial foco en talento junior formado en universidades españolas.',
                'bodyHtml' => '<p>La compañía, fundada en 2019, ya opera en doce países y prevé lanzar nuevos productos para pymes en 2027. El CEO destacó el «ecosistema emprendedor maduro» del país como factor clave para la expansión.</p>',
                'categorySlug' => 'economia',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&q=80', 'caption' => 'Oficinas de la empresa tecnológica.'],
                    ['url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&q=80', 'caption' => 'Equipo de desarrollo durante la presentación.'],
                ],
            ],
            [
                'slug' => 'investigacion-hospital-breakthrough-alzheimer',
                'title' => 'Un hospital público logra un avance en el diagnóstico temprano del alzhéimer',
                'subtitle' => 'Un nuevo protocolo reduce el tiempo de detección de meses a días',
                'lead' => 'El estudio, realizado con más de 2.000 pacientes, combina biomarcadores en sangre y pruebas neurocognitivas para identificar la enfermedad en fases iniciales.',
                'bodyHtml' => '<p>Los investigadores subrayan que el método podría facilitar tratamientos más efectivos si se aplican pronto. El protocolo será evaluado por otras comunidades autónomas en un ensayo multicéntrico.</p><p>La sanidad pública ha invertido en equipamiento de resonancia de alta resolución para implementar la técnica en tres hospitales de referencia.</p>',
                'categorySlug' => 'sociedad',
                'images' => [
                    ['url' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1200&q=80', 'caption' => 'Laboratorio de investigación biomédica.'],
                ],
            ],
        ];
    }
}
