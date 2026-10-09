<?php

namespace App\Games;

final class WordScrambleGenerator
{
    /** @var list<array{word: string, hint: string}> */
    private const WORDS = [
        ['word' => 'PERIODICO', 'hint' => 'Lo que estás leyendo'],
        ['word' => 'NOTICIA', 'hint' => 'Información de actualidad'],
        ['word' => 'ENTREVISTA', 'hint' => 'Charla con una persona'],
        ['word' => 'REPORTAJE', 'hint' => 'Artículo profundo'],
        ['word' => 'COLUMNISTA', 'hint' => 'Firma habitual en el papel'],
        ['word' => 'PORTADA', 'hint' => 'Primera página'],
        ['word' => 'EDITORIAL', 'hint' => 'Opinión del medio'],
        ['word' => 'DEPORTE', 'hint' => 'Fútbol, tenis, etc.'],
        ['word' => 'CULTURA', 'hint' => 'Arte, libros y cine'],
        ['word' => 'SOCIEDAD', 'hint' => 'Temas de la vida cotidiana'],
        ['word' => 'ECONOMIA', 'hint' => 'Dinero y empresas'],
        ['word' => 'POLITICA', 'hint' => 'Gobierno y elecciones'],
        ['word' => 'TECNOLOGIA', 'hint' => 'Innovación digital'],
        ['word' => 'OPINION', 'hint' => 'Punto de vista personal'],
        ['word' => 'LECTOR', 'hint' => 'Quien consume el contenido'],
        ['word' => 'SUSCRIPCION', 'hint' => 'Pago para recibir el medio'],
        ['word' => 'ENTRETENIMIENTO', 'hint' => 'Ocio y diversión'],
        ['word' => 'TELEVISION', 'hint' => 'Pantalla en el salón'],
        ['word' => 'CINE', 'hint' => 'Películas en la gran pantalla'],
        ['word' => 'MUSICA', 'hint' => 'Sonidos y canciones'],
        ['word' => 'VIAJES', 'hint' => 'Ir a otros lugares'],
        ['word' => 'GASTRONOMIA', 'hint' => 'Cocina y restaurantes'],
        ['word' => 'NATURALEZA', 'hint' => 'Montañas, bosques y mar'],
        ['word' => 'HISTORIA', 'hint' => 'El pasado contado'],
        ['word' => 'CIENCIA', 'hint' => 'Investigación y descubrimientos'],
        ['word' => 'SALUD', 'hint' => 'Bienestar y medicina'],
        ['word' => 'FAMILIA', 'hint' => 'Padres, hijos y hogar'],
        ['word' => 'EDUCACION', 'hint' => 'Escuela y aprendizaje'],
        ['word' => 'TRABAJO', 'hint' => 'Empleo y oficina'],
        ['word' => 'VACACIONES', 'hint' => 'Descanso en verano'],
        ['word' => 'MADRID', 'hint' => 'Capital de España'],
        ['word' => 'BARCELONA', 'hint' => 'Ciudad con la Sagrada Familia'],
        ['word' => 'SEVILLA', 'hint' => 'Ciudad andaluza con giralda'],
        ['word' => 'VALENCIA', 'hint' => 'Ciudad de la paella'],
        ['word' => 'BILBAO', 'hint' => 'Ciudad del Guggenheim'],
        ['word' => 'ATLANTICO', 'hint' => 'Océano al oeste de Europa'],
        ['word' => 'MEDITERRANEO', 'hint' => 'Mar entre España y África'],
        ['word' => 'MONTANA', 'hint' => 'Elevación natural'],
        ['word' => 'PLAYA', 'hint' => 'Arena junto al mar'],
        ['word' => 'BIBLIOTECA', 'hint' => 'Lugar lleno de libros'],
        ['word' => 'MUSEO', 'hint' => 'Exposición de arte'],
        ['word' => 'TEATRO', 'hint' => 'Obras en vivo'],
        ['word' => 'FUTBOL', 'hint' => 'Deporte con porterías'],
        ['word' => 'BALONCESTO', 'hint' => 'Canastas y driblings'],
        ['word' => 'TENIS', 'hint' => 'Raqueta y pelota amarilla'],
        ['word' => 'NATACION', 'hint' => 'Deporte en la piscina'],
        ['word' => 'MARATON', 'hint' => 'Carrera de muchos kilómetros'],
        ['word' => 'CAMPEON', 'hint' => 'Ganador de una competición'],
        ['word' => 'ENTRENADOR', 'hint' => 'Dirige al equipo'],
        ['word' => 'ESTADIO', 'hint' => 'Recinto deportivo grande'],
        ['word' => 'GOLEADOR', 'hint' => 'Marca muchos goles'],
        ['word' => 'INFORMACION', 'hint' => 'Datos y novedades'],
        ['word' => 'ACTUALIDAD', 'hint' => 'Lo que pasa ahora'],
        ['word' => 'INVESTIGACION', 'hint' => 'Buscar respuestas'],
        ['word' => 'DOCUMENTAL', 'hint' => 'Película sobre la realidad'],
        ['word' => 'ENTRETENER', 'hint' => 'Divertir al público'],
        ['word' => 'CRUCIGRAMA', 'hint' => 'Pasatiempo con palabras'],
        ['word' => 'SUDOKU', 'hint' => 'Pasatiempo con números'],
        ['word' => 'HOROSCOPO', 'hint' => 'Predicción por signo zodiacal'],
        ['word' => 'ARIES', 'hint' => 'Primer signo del zodiaco'],
        ['word' => 'TAURO', 'hint' => 'Signo del toro'],
        ['word' => 'GEMINIS', 'hint' => 'Signo de los gemelos'],
    ];

    public function generateDaily(string $seed): array
    {
        $index = crc32($seed.'-anagrama') % count(self::WORDS);
        $entry = self::WORDS[$index];
        $word = $entry['word'];
        $scrambled = $this->scramble($word, crc32($seed.'-scramble'));

        return [
            'word' => $word,
            'scrambled' => $scrambled,
            'hint' => $entry['hint'],
        ];
    }

    private function scramble(string $word, int $seed): string
    {
        $letters = mb_strtoupper($word);
        $chars = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $attempts = 0;
        do {
            $this->shuffleChars($chars, $seed + $attempts);
            $scrambled = implode('', $chars);
            ++$attempts;
        } while ($scrambled === $letters && $attempts < 10);

        return $scrambled;
    }

    /** @param list<string> $chars */
    private function shuffleChars(array &$chars, int $seed): void
    {
        $count = count($chars);
        for ($i = $count - 1; $i > 0; --$i) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $j = $seed % ($i + 1);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
    }
}
