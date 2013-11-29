<?php


return array(
    'MAX_TIME' => 60,
    //После какого кол-ва секунд сбросить ордер?
    'PROFIT_LVL' => 0.07,
    //Какой профит нас устроит?
    //% от покупки. Т.е. если купили на 1000, то прибыль от сделки при профите 10% должна быть не менее 100
    'ORDER_MIN_AMOUNT' => array(
        'rur' => 280,
        'usd' => 1,
    ),
    //Сумма одного ордера в текущей валюте
    'COMISSION' => 0.002, //какая комиссия взимается с опреации?
    'RATE_DIFF' => 0.015,
    //Берем коридор от текущего курса. Если на нем уже есть ордеры, то новый ордер делать бессмысленно,
    //иначе мы сольем все деньги на одном курсе
    'RATE_UP_AVG' => 0.1, //На сколько выше среднего курса проходит граница максимальной закупки?
    'RETURN_LVL' => 0.015,
    // При пробивке этой линии, ситаем, что произошел разворот тренда. Линия проводиться Под/над локальным экстремумом

);