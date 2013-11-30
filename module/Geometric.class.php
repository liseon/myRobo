<?php

class Geometric
{
    /**
     * Считаем угло между прмями
     * @param $line1
     * @param $line2
     */
    public static function calcAngle($line1, $line2) {
        $ang1 = atan($line1[0] - $line1[1]);
        $ang2 = atan($line2[0] - $line2[1]);
        $ang = $ang1 - $ang2;

        $ang = ($ang / pi()) * 180;

        return $ang;
    }
}