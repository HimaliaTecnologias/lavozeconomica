<?php

namespace App\Horoscope;

final class HoroscopeFallbackContent
{
    private const HEADLINES = [
        'Un día para confiar en tu intuición',
        'Pequeños cambios traen grandes resultados',
        'La calma será tu mejor aliada',
        'Oportunidades en el entorno cercano',
        'Energía renovada para tus proyectos',
        'Buen momento para conversar con claridad',
        'Prioriza lo que realmente importa',
        'Sorpresa agradable en el horizonte',
        'Constancia y paciencia marcan la jornada',
        'Abre espacio a nuevas ideas',
        'Cuida los detalles sin perder el foco',
        'La colaboración suma más que la prisa',
    ];

    private const PARAGRAPHS = [
        'Hoy conviene escuchar antes de actuar. Si algo te genera dudas, tómate un respiro y vuelve con perspectiva. En lo personal, un gesto sencillo puede mejorar el ambiente.',
        'Las tareas pendientes piden orden, pero sin agobio. Organiza tu agenda y deja huecos para improvisar. En el trabajo, una conversación directa evitará malentendidos.',
        'Tu energía está en alza, así que canalízala en algo concreto. Evita dispersarte en mil frentes. Si buscas inspiración, un cambio de rutina te sentará bien.',
        'Es un día favorable para cerrar asuntos pequeños que llevas arrastrando. No necesitas grandes gestos: la constancia hará el resto. Cuídate el descanso.',
        'La sensibilidad está más alta de lo habitual, así que elige bien tus palabras. En temas económicos, mejor revisar antes de comprometerse. La tarde trae claridad.',
        'Un encuentro casual puede abrirte una puerta inesperada. Mantén la curiosidad y el buen humor. En casa, pequeños arreglos mejorarán el confort.',
        'Si algo no fluye, no insistas en la misma dirección. Prueba un enfoque distinto y verás resultados. El autocuidado no es un lujo: es necesario hoy.',
        'La comunicación es la clave de la jornada. Expresa lo que piensas con respeto y escucha las propuestas de otros. Una decisión tomada con calma será acertada.',
        'Hay margen para disfrutar sin culpa: reserva tiempo para un hobby o una salida breve. En lo profesional, tu experiencia previa te dará ventaja.',
        'Evita compararte con nadie. Tu ritmo es válido y tus logros también. Si surge un conflicto, busca el punto medio antes de que crezca.',
        'La intuición te señala un camino, pero los datos confirman el plan. Combina ambos. Una compra o trámite se resuelve mejor a primera hora.',
        'El entusiasmo vuelve si te permites celebrar lo que ya has conseguido. Comparte tus ideas con alguien de confianza: recibirás buenos consejos.',
    ];

    /**
     * @return array{headline: string, text: string}
     */
    public function forSign(string $slug, string $dateKey): array
    {
        $seed = crc32($dateKey.'-'.$slug);
        $headlineIndex = $seed % count(self::HEADLINES);
        $paragraphIndex = ($seed >> 3) % count(self::PARAGRAPHS);

        return [
            'headline' => self::HEADLINES[$headlineIndex],
            'text' => self::PARAGRAPHS[$paragraphIndex],
        ];
    }
}
