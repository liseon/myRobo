<?php

class Geometric
{
    /**
     * Считаем угол между прямыми
     * @param $line1
     * @param $line2
     */
    public static function calcAngle($line1, $line2) {
		// Будем считать, что шаг по оси X эквивалентен масштабу 0.1% от значения цены по Y
		$prc = $line2[1]/1000;  
        $ang1 = atan(($line1[1] - $line1[0])/$prc);
        $ang2 = atan(($line2[1] - $line2[0])/$prc);
        $ang = abs($ang1 - $ang2);

        $ang = ($ang / pi()) * 180;

        return $ang;
    }
}