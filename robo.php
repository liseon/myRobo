<?php

require_once('include.php');

$trader = TraderLogic::getInstance();
$trader->setPair('ltc_rur');
$i=0;

echo "Start \n";
echo "MIN_AMOUNT: " . $trader->getOrderMinAmount() . "\n";
$bill = $trader->myBill();
$date = date("m-d-Y H:i");
echo "{$date}: rur: {$bill['rur']}  ltc: {$bill['ltc']} \n";

while (1 == 1) {
    //Закупаем валюту
    $trader->ordersCreate();

    //Фиксим прибыль по открытым ордерам!
    $trader->ordersCloser();

    //Синхронизируем открытые ордера
    $trader->ordersUpdate();


    $i++;
    if ($i>=300) {
        $i = 0;
        $bill = $trader->myBill();
        $date = date("m-d-Y H:i");
        echo "{$date}: rur: {$bill['rur']}  ltc: {$bill['ltc']} \n";
    }

    //Отдыхаем
    sleep(2);
}



