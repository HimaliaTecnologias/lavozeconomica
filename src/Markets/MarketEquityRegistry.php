<?php

namespace App\Markets;

/**
 * Cotizaciones de acciones españolas (IBEX 35 y Mercado Continuo).
 */
final class MarketEquityRegistry
{
    /**
     * @return list<array{id: string, name: string, symbol: string, ticker: string, market: string}>
     */
    public static function all(): array
    {
        return array_merge(self::ibex35(), self::continuo());
    }

    /**
     * Componentes del IBEX 35 (Yahoo Finance .MC).
     *
     * @return list<array{id: string, name: string, symbol: string, ticker: string, market: string}>
     */
    public static function ibex35(): array
    {
        return [
            self::eq('acs', 'ACS', 'ACS.MC', 'ACS', 'ibex35'),
            self::eq('acerinox', 'Acerinox', 'ACX.MC', 'ACX', 'ibex35'),
            self::eq('aena', 'Aena', 'AENA.MC', 'AENA', 'ibex35'),
            self::eq('amadeus', 'Amadeus', 'AMS.MC', 'AMS', 'ibex35'),
            self::eq('acciona', 'Acciona', 'ANA.MC', 'ANA', 'ibex35'),
            self::eq('acciona_energia', 'Acciona Energía', 'ANE.MC', 'ANE', 'ibex35'),
            self::eq('bbva', 'BBVA', 'BBVA.MC', 'BBVA', 'ibex35'),
            self::eq('bankinter', 'Bankinter', 'BKT.MC', 'BKT', 'ibex35'),
            self::eq('caixabank', 'CaixaBank', 'CABK.MC', 'CABK', 'ibex35'),
            self::eq('cellnex', 'Cellnex', 'CLNX.MC', 'CLNX', 'ibex35'),
            self::eq('colonial', 'Colonial', 'COL.MC', 'COL', 'ibex35'),
            self::eq('endesa', 'Endesa', 'ELE.MC', 'ELE', 'ibex35'),
            self::eq('enagas', 'Enagás', 'ENG.MC', 'ENG', 'ibex35'),
            self::eq('ferrovial', 'Ferrovial', 'FER.MC', 'FER', 'ibex35'),
            self::eq('fluidra', 'Fluidra', 'FDR.MC', 'FDR', 'ibex35'),
            self::eq('grifols', 'Grifols', 'GRF.MC', 'GRF', 'ibex35'),
            self::eq('iag', 'IAG', 'IAG.MC', 'IAG', 'ibex35'),
            self::eq('iberdrola', 'Iberdrola', 'IBE.MC', 'IBE', 'ibex35'),
            self::eq('indra', 'Indra', 'IDR.MC', 'IDR', 'ibex35'),
            self::eq('inditex', 'Inditex', 'ITX.MC', 'ITX', 'ibex35'),
            self::eq('logista', 'Logista', 'LOG.MC', 'LOG', 'ibex35'),
            self::eq('mapfre', 'Mapfre', 'MAP.MC', 'MAP', 'ibex35'),
            self::eq('melia', 'Meliá Hotels', 'MEL.MC', 'MEL', 'ibex35'),
            self::eq('merlin', 'Merlin Properties', 'MRL.MC', 'MRL', 'ibex35'),
            self::eq('arcelormittal', 'ArcelorMittal', 'MTS.MC', 'MTS', 'ibex35'),
            self::eq('naturgy', 'Naturgy', 'NTGY.MC', 'NTGY', 'ibex35'),
            self::eq('redeia', 'Redeia', 'RED.MC', 'RED', 'ibex35'),
            self::eq('repsol', 'Repsol', 'REP.MC', 'REP', 'ibex35'),
            self::eq('rovi', 'Laboratorios Rovi', 'ROVI.MC', 'ROVI', 'ibex35'),
            self::eq('sabadell', 'Banco Sabadell', 'SAB.MC', 'SAB', 'ibex35'),
            self::eq('santander', 'Banco Santander', 'SAN.MC', 'SAN', 'ibex35'),
            self::eq('sacyr', 'Sacyr', 'SCYR.MC', 'SCYR', 'ibex35'),
            self::eq('solaria', 'Solaria', 'SLR.MC', 'SLR', 'ibex35'),
            self::eq('telefonica', 'Telefónica', 'TEF.MC', 'TEF', 'ibex35'),
            self::eq('unicaja', 'Unicaja Banco', 'UNI.MC', 'UNI', 'ibex35'),
        ];
    }

    /**
     * Valores destacados del Mercado Continuo (fuera del IBEX 35).
     *
     * @return list<array{id: string, name: string, symbol: string, ticker: string, market: string}>
     */
    public static function continuo(): array
    {
        return [
            self::eq('almirall', 'Almirall', 'ALM.MC', 'ALM', 'continuo'),
            self::eq('cie', 'CIE Automotive', 'CIE.MC', 'CIE', 'continuo'),
            self::eq('viscofan', 'Viscofan', 'VIS.MC', 'VIS', 'continuo'),
            self::eq('fcc', 'FCC', 'FCC.MC', 'FCC', 'continuo'),
            self::eq('pharmamar', 'PharmaMar', 'PHM.MC', 'PHM', 'continuo'),
            self::eq('catalana', 'Catalana Occidente', 'GCO.MC', 'GCO', 'continuo'),
            self::eq('caf', 'CAF', 'CAF.MC', 'CAF', 'continuo'),
            self::eq('ohla', 'OHLA', 'OHLA.MC', 'OHLA', 'continuo'),
            self::eq('tubacex', 'Tubacex', 'TUB.MC', 'TUB', 'continuo'),
            self::eq('vidrala', 'Vidrala', 'VID.MC', 'VID', 'continuo'),
            self::eq('ebro', 'Ebro Foods', 'EBRO.MC', 'EBRO', 'continuo'),
            self::eq('prosegur', 'Prosegur', 'PSG.MC', 'PSG', 'continuo'),
            self::eq('nh', 'NH Hotel Group', 'NHH.MC', 'NHH', 'continuo'),
            self::eq('tecnicas', 'Técnicas Reunidas', 'TRE.MC', 'TRE', 'continuo'),
            self::eq('encer', 'Ence', 'ENC.MC', 'ENC', 'continuo'),
        ];
    }

    /**
     * @return array{id: string, name: string, symbol: string, ticker: string, market: string}
     */
    private static function eq(string $id, string $name, string $symbol, string $ticker, string $market): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'symbol' => $symbol,
            'ticker' => $ticker,
            'market' => $market,
        ];
    }

    public static function marketLabel(string $market): string
    {
        return match ($market) {
            'ibex35' => 'IBEX 35',
            'continuo' => 'Mercado Continuo',
            default => 'Acciones',
        };
    }
}
