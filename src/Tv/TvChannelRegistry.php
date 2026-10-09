<?php

namespace App\Tv;

final class TvChannelRegistry
{
    /**
     * Canales por defecto: generalistas nacionales + principales autonómicas.
     *
     * @var list<string>
     */
    public const DEFAULT_CHANNEL_IDS = [
        'la1',
        'la2',
        'antena3',
        'cuatro',
        'telecinco',
        'lasexta',
        'trece',
        'teledeporte',
        '24h',
        'telemadrid',
        'laotra',
        'tv3',
        'canalsur',
        'etb1',
        'etb2',
        'tvg',
        'aragontv',
        'apunt',
        'ib3',
        'tvcanaria',
        'canalextremadura',
        'clm',
        'tpa',
        'navarra',
        'la7murcia',
        'andaluciatv',
    ];

    /**
     * @return list<array{id: string, name: string, xmltvId: string, group: string, description: string}>
     */
    public static function all(): array
    {
        return [
            // —— Generalistas ——
            [
                'id' => 'la1',
                'name' => 'La 1',
                'xmltvId' => 'LA1.es',
                'group' => 'generalista',
                'description' => 'TVE — canal generalista público.',
            ],
            [
                'id' => 'la2',
                'name' => 'La 2',
                'xmltvId' => 'LA2.es',
                'group' => 'generalista',
                'description' => 'TVE — cultura, documentales y deporte.',
            ],
            [
                'id' => 'antena3',
                'name' => 'Antena 3',
                'xmltvId' => 'Antena3.es',
                'group' => 'generalista',
                'description' => 'Series, entretenimiento e informativos.',
            ],
            [
                'id' => 'cuatro',
                'name' => 'Cuatro',
                'xmltvId' => 'Cuatro.es',
                'group' => 'generalista',
                'description' => 'Entretenimiento y realities.',
            ],
            [
                'id' => 'telecinco',
                'name' => 'Telecinco',
                'xmltvId' => 'Telecinco.es',
                'group' => 'generalista',
                'description' => 'Ficción, realities y prime time.',
            ],
            [
                'id' => 'lasexta',
                'name' => 'La Sexta',
                'xmltvId' => 'LaSexta.es',
                'group' => 'generalista',
                'description' => 'Información, humor y entretenimiento.',
            ],
            [
                'id' => 'trece',
                'name' => 'TRECE',
                'xmltvId' => 'TRECE.es',
                'group' => 'generalista',
                'description' => 'Canal generalista (antes 13 TV).',
            ],
            [
                'id' => 'teledeporte',
                'name' => 'Teledeporte',
                'xmltvId' => 'Teledeporte.es',
                'group' => 'tematico',
                'description' => 'Deporte en abierto de RTVE.',
            ],
            [
                'id' => '24h',
                'name' => '24 Horas',
                'xmltvId' => '24Horas.es',
                'group' => 'informacion',
                'description' => 'Informativo continuo de TVE.',
            ],
            [
                'id' => 'clan',
                'name' => 'Clan',
                'xmltvId' => 'ClanTVE.es',
                'group' => 'infantil',
                'description' => 'Infantil y juvenil de RTVE.',
            ],

            // —— Temáticos en abierto ——
            [
                'id' => 'neox',
                'name' => 'Neox',
                'xmltvId' => 'Neox.es',
                'group' => 'tematico',
                'description' => 'Juvenil y series internacionales.',
            ],
            [
                'id' => 'nova',
                'name' => 'Nova',
                'xmltvId' => 'Nova.es',
                'group' => 'tematico',
                'description' => 'Ficción y lifestyle.',
            ],
            [
                'id' => 'mega',
                'name' => 'Mega',
                'xmltvId' => 'Mega.es',
                'group' => 'tematico',
                'description' => 'Cine y series de acción.',
            ],
            [
                'id' => 'atreseries',
                'name' => 'Atreseries',
                'xmltvId' => 'Atreseries.es',
                'group' => 'tematico',
                'description' => 'Series de Atresmedia.',
            ],
            [
                'id' => 'energy',
                'name' => 'Energy',
                'xmltvId' => 'Energy.es',
                'group' => 'tematico',
                'description' => 'Acción y realities.',
            ],
            [
                'id' => 'divinity',
                'name' => 'Divinity',
                'xmltvId' => 'Divinity.es',
                'group' => 'tematico',
                'description' => 'Lifestyle y ficción.',
            ],
            [
                'id' => 'fdf',
                'name' => 'FDF',
                'xmltvId' => 'FactoriadeFiccion.es',
                'group' => 'tematico',
                'description' => 'Factoría de Ficción (Mediaset).',
            ],
            [
                'id' => 'bemad',
                'name' => 'Be Mad',
                'xmltvId' => 'BEMAD.es',
                'group' => 'tematico',
                'description' => 'Documentales y entretenimiento.',
            ],
            [
                'id' => 'boing',
                'name' => 'Boing',
                'xmltvId' => 'Boing.es',
                'group' => 'infantil',
                'description' => 'Infantil de Mediaset.',
            ],
            [
                'id' => 'paramount',
                'name' => 'Paramount Network',
                'xmltvId' => 'ParamountChannel.es',
                'group' => 'tematico',
                'description' => 'Cine y series.',
            ],
            [
                'id' => 'dkiss',
                'name' => 'DKISS',
                'xmltvId' => 'DKISS.es',
                'group' => 'tematico',
                'description' => 'Documentales y realities.',
            ],
            [
                'id' => 'gol',
                'name' => 'GOL',
                'xmltvId' => 'GOL.es',
                'group' => 'tematico',
                'description' => 'Fútbol y deporte en abierto.',
            ],

            // —— Autonómicas ——
            [
                'id' => 'telemadrid',
                'name' => 'Telemadrid',
                'xmltvId' => 'Telemadrid.es',
                'group' => 'autonomica',
                'description' => 'Televisión de la Comunidad de Madrid.',
            ],
            [
                'id' => 'laotra',
                'name' => 'La Otra',
                'xmltvId' => 'LaOtra.es',
                'group' => 'autonomica',
                'description' => 'Segundo canal de Telemadrid.',
            ],
            [
                'id' => 'tv3',
                'name' => 'TV3',
                'xmltvId' => 'TV3.es',
                'group' => 'autonomica',
                'description' => 'Televisió de Catalunya.',
            ],
            [
                'id' => '324',
                'name' => '3/24',
                'xmltvId' => '324.es',
                'group' => 'autonomica',
                'description' => 'Canal de información de TV3.',
            ],
            [
                'id' => 'esport3',
                'name' => 'Esport3',
                'xmltvId' => 'Esport3.es',
                'group' => 'autonomica',
                'description' => 'Deporte de TV3.',
            ],
            [
                'id' => 'canalsur',
                'name' => 'Canal Sur',
                'xmltvId' => 'CanalSur.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Andalucía.',
            ],
            [
                'id' => 'canalsurandalucia',
                'name' => 'Canal Sur Andalucía',
                'xmltvId' => 'CanalSurAndalucia.es',
                'group' => 'autonomica',
                'description' => 'Señal de Canal Sur para el resto de España.',
            ],
            [
                'id' => 'andaluciatv',
                'name' => 'Andalucía TV',
                'xmltvId' => 'AndaluciaTV.es',
                'group' => 'autonomica',
                'description' => 'Canal autonómico andaluz.',
            ],
            [
                'id' => 'etb1',
                'name' => 'ETB1',
                'xmltvId' => 'ETB1.es',
                'group' => 'autonomica',
                'description' => 'Euskal Telebista (euskera).',
            ],
            [
                'id' => 'etb2',
                'name' => 'ETB2',
                'xmltvId' => 'ETB2.es',
                'group' => 'autonomica',
                'description' => 'Euskal Telebista (castellano).',
            ],
            [
                'id' => 'etb3',
                'name' => 'ETB3',
                'xmltvId' => 'ETB3.es',
                'group' => 'autonomica',
                'description' => 'Canal juvenil de EITB.',
            ],
            [
                'id' => 'tvg',
                'name' => 'TVG',
                'xmltvId' => 'TVG-TVGalicia.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Galicia.',
            ],
            [
                'id' => 'tvg2',
                'name' => 'TVG2',
                'xmltvId' => 'TVG2.es',
                'group' => 'autonomica',
                'description' => 'Segundo canal de TVG.',
            ],
            [
                'id' => 'aragontv',
                'name' => 'Aragón TV',
                'xmltvId' => 'AragonTV.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Aragón.',
            ],
            [
                'id' => 'apunt',
                'name' => 'À Punt',
                'xmltvId' => 'APunt.es',
                'group' => 'autonomica',
                'description' => 'Televisión de la Comunitat Valenciana.',
            ],
            [
                'id' => 'ib3',
                'name' => 'IB3',
                'xmltvId' => 'IB3.es',
                'group' => 'autonomica',
                'description' => 'Televisión de las Illes Balears.',
            ],
            [
                'id' => 'canalextremadura',
                'name' => 'Canal Extremadura',
                'xmltvId' => 'CanalExtremadura.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Extremadura.',
            ],
            [
                'id' => 'clm',
                'name' => 'CMM TV',
                'xmltvId' => 'CastillalaManchaTV.es',
                'group' => 'autonomica',
                'description' => 'Castilla-La Mancha Media.',
            ],
            [
                'id' => 'tvcanaria',
                'name' => 'TV Canaria',
                'xmltvId' => 'TVCanaria.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Canarias.',
            ],
            [
                'id' => 'tpa',
                'name' => 'TPA',
                'xmltvId' => 'TPA.es',
                'group' => 'autonomica',
                'description' => 'Televisión del Principado de Asturias.',
            ],
            [
                'id' => 'navarra',
                'name' => 'Navarra Televisión',
                'xmltvId' => 'NavarraTelevision.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Navarra.',
            ],
            [
                'id' => 'la7murcia',
                'name' => 'La 7 Murcia',
                'xmltvId' => 'La7.es',
                'group' => 'autonomica',
                'description' => 'Televisión de la Región de Murcia.',
            ],
            [
                'id' => 'beteve',
                'name' => 'Betevé',
                'xmltvId' => 'Beteve.es',
                'group' => 'autonomica',
                'description' => 'Televisión de Barcelona.',
            ],
        ];
    }

    /** @return array{id: string, name: string, xmltvId: string, group: string, description: string}|null */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $channel) {
            if ($channel['id'] === $id) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public static function normalizeIds(array $ids): array
    {
        $valid = array_column(self::all(), 'id');
        $normalized = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !in_array($id, $valid, true)) {
                continue;
            }
            if (!in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        return $normalized !== [] ? $normalized : self::DEFAULT_CHANNEL_IDS;
    }

    /**
     * @return array<string, list<array{id: string, name: string, xmltvId: string, group: string, description: string}>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $channel) {
            $groups[$channel['group']][] = $channel;
        }

        return $groups;
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'generalista' => 'Generalistas',
            'autonomica' => 'Autonómicas',
            'tematico' => 'Temáticos',
            'informacion' => 'Información',
            'infantil' => 'Infantil',
            default => 'Otros',
        };
    }

    /**
     * Orden de grupos para formularios y listados.
     *
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return ['generalista', 'autonomica', 'informacion', 'tematico', 'infantil'];
    }
}
