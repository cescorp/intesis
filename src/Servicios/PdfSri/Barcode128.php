<?php
/***********************************************************************
 * GENERADOR CODE 128
 * Copiado del subproyecto importar_sri_leer_xml/lib/pdf_sri/Barcode128.php
 * No depende de base de datos ni de rutas externas.
 **********************************************************************/

if (!function_exists('gpdf_barcode_code128_svg')) {
    function gpdf_barcode_code128_svg($texto, $ancho = 330, $alto = 44)
    {
        $texto = (string) $texto;
        if ($texto === '') {
            return '';
        }

        $patrones = array(
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112'
        );

        $codigos = array(104);
        $checksum = 104;

        for ($i = 0; $i < strlen($texto); $i++) {
            $valor = ord($texto[$i]) - 32;
            if ($valor < 0 || $valor > 95) {
                $valor = 0;
            }
            $codigos[] = $valor;
            $checksum += $valor * count($codigos) - $valor;
        }

        $codigos[] = $checksum % 103;
        $codigos[] = 106;

        $modulos = 0;
        foreach ($codigos as $codigo) {
            foreach (str_split($patrones[$codigo]) as $digito) {
                $modulos += (int) $digito;
            }
        }

        $escala = $modulos > 0 ? ($ancho / $modulos) : 1;
        $x = 0;
        $rectangulos = '';

        foreach ($codigos as $codigo) {
            $partes = str_split($patrones[$codigo]);
            foreach ($partes as $indice => $digito) {
                $w = ((int) $digito) * $escala;
                if ($indice % 2 === 0) {
                    $rectangulos .= '<rect x="' . round($x, 2) . '" y="0" width="' . round($w, 2) . '" height="' . (int) $alto . '" fill="#000"/>';
                }
                $x += $w;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $ancho . '" height="' . (int) $alto . '" viewBox="0 0 ' . (int) $ancho . ' ' . (int) $alto . '">' . $rectangulos . '</svg>';
    }
}

if (!function_exists('gpdf_barcode_texto_clave')) {
    function gpdf_barcode_texto_clave($claveAcceso)
    {
        return trim(chunk_split((string) $claveAcceso, 4, ' '));
    }
}
