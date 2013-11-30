<?php

require_once('include.php');

$trader = TraderLogic::getInstance();
$trader->setPair('btc_rur');
$i=0;

echo "Start \n";
echo "MIN_AMOUNT: " . $trader->getOrderMinAmount() . "\n";

$trader->printStat();

while (1 == 1) {
    //Закупаем валюту
    $trader->ordersCreate();

    //Фиксим прибыль по открытым ордерам!
    $trader->ordersCloser();

    //Синхронизируем открытые ордера
    $trader->ordersUpdate();


    $i++;

    if ($i % 10 == 0) {
        Adviser::getInstance()->echoTrend();
    }

    if ($i>=100) {
        $i = 0;
        $trader->printStat();
    }

    //Отдыхаем
    sleep(2);
}



