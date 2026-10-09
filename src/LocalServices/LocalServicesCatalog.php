<?php

namespace App\LocalServices;

final class LocalServicesCatalog
{
    public const TYPES = [
        'trafico' => [
            'title' => 'Tráfico',
            'intro' => 'Estado del tráfico y rutas cerca de tu código postal.',
            'route' => 'public_local_traffic',
        ],
        'transporte' => [
            'title' => 'Transporte',
            'intro' => 'Transporte público y desplazamientos según tu zona.',
            'route' => 'public_local_transport',
        ],
        'farmacias' => [
            'title' => 'Farmacias',
            'intro' => 'Farmacias cercanas y de guardia según tu código postal.',
            'route' => 'public_local_pharmacies',
        ],
        'telefonos-utiles' => [
            'title' => 'Teléfonos útiles',
            'intro' => 'Emergencias y teléfonos de interés para tu localidad.',
            'route' => 'public_local_phones',
        ],
    ];

    /**
     * @return array{
     *     links: list<array{label: string, url: string, hint?: string}>,
     *     phones: list<array{number: string, label: string, hint?: string}>,
     *     mapEmbedUrl: string|null
     * }
     */
    public function build(string $type, LocalServicesLocation $location): array
    {
        return match ($type) {
            'trafico' => $this->traffic($location),
            'transporte' => $this->transport($location),
            'farmacias' => $this->pharmacies($location),
            'telefonos-utiles' => $this->phones($location),
            default => ['links' => [], 'phones' => [], 'mapEmbedUrl' => null],
        };
    }

    /**
     * @return array{links: list<array{label: string, url: string, hint?: string}>, phones: list<array{number: string, label: string, hint?: string}>, mapEmbedUrl: string|null}
     */
    private function traffic(LocalServicesLocation $location): array
    {
        $lat = $location->latitude;
        $lon = $location->longitude;

        $links = [
            [
                'label' => 'Tráfico en vivo (Google Maps)',
                'url' => sprintf('https://www.google.com/maps/@%s,%s,13z/data=!5m1!1e1', $lat, $lon),
                'hint' => 'Capa de tráfico alrededor de '.$location->placeName,
            ],
            [
                'label' => 'Estado del tráfico — DGT',
                'url' => 'https://www.dgt.es/conoce-el-estado-del-trafico/',
                'hint' => 'Incidencias y retenciones a nivel nacional',
            ],
            [
                'label' => 'Cómo llegar a '.$location->placeName,
                'url' => sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s', $lat, $lon),
                'hint' => 'Rutas en coche hasta el CP '.$location->postalCode,
            ],
        ];

        if ($location->isMadridProvince()) {
            $links[] = [
                'label' => 'Movilidad — Ayuntamiento de Madrid',
                'url' => 'https://www.madrid.es/portales/munimadrid/es/Inicio/Movilidad-y-transportes/',
                'hint' => 'Información municipal de movilidad',
            ];
            $links[] = [
                'label' => 'Calle 30 — estado',
                'url' => 'https://www.mc30.es/',
                'hint' => 'Incidencias en M-30',
            ];
        }

        return [
            'links' => $links,
            'phones' => [
                ['number' => '011', 'label' => 'Información de tráfico (DGT)', 'hint' => 'Desde España'],
            ],
            'mapEmbedUrl' => null,
        ];
    }

    /**
     * @return array{links: list<array{label: string, url: string, hint?: string}>, phones: list<array{number: string, label: string, hint?: string}>, mapEmbedUrl: string|null}
     */
    private function transport(LocalServicesLocation $location): array
    {
        $lat = $location->latitude;
        $lon = $location->longitude;

        $links = [
            [
                'label' => 'Transporte público cerca (Google Maps)',
                'url' => sprintf('https://www.google.com/maps/search/transporte+público/@%s,%s,14z', $lat, $lon),
                'hint' => 'Paradas y líneas próximas a '.$location->placeName,
            ],
            [
                'label' => 'Ir en transporte público',
                'url' => sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s&travelmode=transit', $lat, $lon),
                'hint' => 'Itinerario hasta CP '.$location->postalCode,
            ],
            [
                'label' => 'Renfe',
                'url' => 'https://www.renfe.com/',
                'hint' => 'Trenes de media y larga distancia',
            ],
        ];

        if ($location->isMadridProvince()) {
            array_unshift($links, [
                'label' => 'Consorcio Regional de Transportes (CRTM)',
                'url' => 'https://www.crtm.es/',
                'hint' => 'Metro, EMT, interurbanos y Cercanías',
            ], [
                'label' => 'Metro de Madrid',
                'url' => 'https://www.metromadrid.es/',
            ], [
                'label' => 'EMT Madrid',
                'url' => 'https://www.emtmadrid.es/',
            ], [
                'label' => 'Cercanías Madrid',
                'url' => 'https://www.renfe.com/es/es/cercanias/cercanias-madrid',
            ]);
        }

        return [
            'links' => $links,
            'phones' => $location->isMadridProvince()
                ? [['number' => '012', 'label' => 'Atención CRTM / Comunidad de Madrid']]
                : [],
            'mapEmbedUrl' => null,
        ];
    }

    /**
     * @return array{links: list<array{label: string, url: string, hint?: string}>, phones: list<array{number: string, label: string, hint?: string}>, mapEmbedUrl: string|null}
     */
    private function pharmacies(LocalServicesLocation $location): array
    {
        $lat = $location->latitude;
        $lon = $location->longitude;
        $cp = $location->postalCode;

        $links = [
            [
                'label' => 'Farmacias cerca en el mapa',
                'url' => sprintf('https://www.google.com/maps/search/farmacia/@%s,%s,15z', $lat, $lon),
                'hint' => 'Resultados alrededor de '.$location->placeName.' ('.$cp.')',
            ],
            [
                'label' => 'Buscar farmacia de guardia',
                'url' => 'https://www.farmaceuticos.com/farmaceuticos/farmacias-de-guardia/',
                'hint' => 'Directorio del Consejo General de Colegios Farmacéuticos',
            ],
        ];

        if ($location->isMadridProvince()) {
            array_unshift($links, [
                'label' => 'Farmacias de guardia — COFM',
                'url' => 'https://www.cofm.es/es/ciudadanos/farmacias-de-guardia/',
                'hint' => 'Colegio Oficial de Farmacéuticos de Madrid · indica CP '.$cp,
            ], [
                'label' => 'Farmacias de guardia — Comunidad de Madrid',
                'url' => 'https://www.comunidad.madrid/servicios/salud/farmacias-guardia',
            ]);
        }

        return [
            'links' => $links,
            'phones' => [
                ['number' => '112', 'label' => 'Emergencias', 'hint' => 'Si necesitas asistencia urgente'],
            ],
            'mapEmbedUrl' => null,
        ];
    }

    /**
     * @return array{links: list<array{label: string, url: string, hint?: string}>, phones: list<array{number: string, label: string, hint?: string}>, mapEmbedUrl: string|null}
     */
    private function phones(LocalServicesLocation $location): array
    {
        $phones = [
            ['number' => '112', 'label' => 'Emergencias', 'hint' => 'Urgencias, incendios, salvamento'],
            ['number' => '061', 'label' => 'Urgencias sanitarias'],
            ['number' => '091', 'label' => 'Policía Nacional'],
            ['number' => '062', 'label' => 'Guardia Civil'],
            ['number' => '092', 'label' => 'Policía Local', 'hint' => 'Ayuntamiento de '.$location->placeName],
            ['number' => '016', 'label' => 'Violencia de género', 'hint' => 'Atención 24 h'],
            ['number' => '024', 'label' => 'Prevención del suicidio'],
        ];

        $links = [
            [
                'label' => 'Ayuntamiento / servicios en el mapa',
                'url' => sprintf(
                    'https://www.google.com/maps/search/ayuntamiento/@%s,%s,14z',
                    $location->latitude,
                    $location->longitude,
                ),
                'hint' => 'Localiza oficinas municipales cerca de '.$location->placeName,
            ],
        ];

        if ($location->isMadridProvince()) {
            array_splice($phones, 5, 0, [
                ['number' => '010', 'label' => 'Información Ayuntamiento de Madrid'],
                ['number' => '012', 'label' => 'Atención ciudadana — Comunidad de Madrid'],
            ]);
            $links[] = [
                'label' => '012 Comunidad de Madrid',
                'url' => 'https://www.comunidad.madrid/servicios/administracion-digital-atencion-ciudadano/012',
            ];
        }

        return [
            'links' => $links,
            'phones' => $phones,
            'mapEmbedUrl' => null,
        ];
    }
}
