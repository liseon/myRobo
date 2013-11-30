<?php
/**
 *  Тут базовые методы, синглтон и подключение к БД
 */

class Trader
{
    /**
     * @var BTCeAPI
     */
    protected $API;

    protected $BD;

    protected $config;

    protected $virtual = true;

    private $table = TABLE_ORDERS_VIRTUAL;

    /**
     * @var array virtual
     */
    protected $bill;

    protected function __construct() {
        $this->config = ConfigHelper::getInstance();
        $this->API = ApiHelper::getInstance();

        $this->BD = new mysqli(
            $this->config->getSecret('BD_HOST'),
            $this->config->getSecret('BD_USER'),
            $this->config->getSecret('BD_PW'),
            $this->config->getSecret('BD_NAME')
        )
        or die("Error " . mysqli_error($this->bd));

        $this->bill = MyBill::getInstance();
    }

    public function setVirtual($virtual = true) {
        $this->virtual = $virtual;
        if ($virtual) {
            $this->table = TABLE_ORDERS_VIRTUAL;
        } else {
            $this->table = TABLE_ORDERS;
        }
    }

    public function setPair($pair) {
        $this->bill->setPair($pair);
    }

    public function getOrderMinAmount() {
        $min = $this->config->get('ORDER_MIN_AMOUNT');

        return $min[$this->bill->getCurrFrom()];
    }



    /**
     * Печатает статистику.
     * Если на виртуальном счету больше, чем на реальном, то ошибка!  Переводим в вирутальный режим!
     */
    public function printStat() {
        $date = date("m-d-Y H:i");
        $from = $this->bill->getCurrFrom();
        $to = $this->bill->getCurrTo();
        if ($this->virtual) {
            $mode = "virtual";
        } else {
            $mode = "real";
        }
        echo "{$date}: MODE: {$mode} =============== \n";
        if (!$this->virtual) {
            $info = MyBill::getInfo();
            $real = $info['funds'];

            if (
                $real[$from] < $this->bill->myBill('from')
                || $real[$to] < $this->bill->myBill('to')
            ) {
                echo "ERROR! Real bill < Virtual! \n Virtual mode ON!";
                $this->setVirtual(true);
            }
            echo "REAL: {$from}: {$real[$from]}";
            echo "{$to}: {$real[$to]} \n";
        }

        echo "VIRTUAL: {$from}: {$this->bill->myBill('from')} ";
        echo "{$to}: {$this->bill->myBill('to')} \n";
    }


    /**
     * Проверим, можно ли сделать ордер!
     * Коридор - это +- на шкале курса от существующей ставки
     * Проверим сигнал:
     *     0 - нет сигнала
     *     1 - можно сделать ставку, если она не последняя
     *     2 - разворот на растущий тренд - игнорируем коридор
     *     3 - пробили среднюю линию с хорошим углом! Можно потратить последнюю ставку!
     * Нельзя, если мало денег!
     * Если денег у нас на 2 и более ордеров, то работаем по стандартной ставке
     * Если Денег меньше, чем на 2 ставки, то поставим сразу все, но при сильном сигнале!
     * @param $rate
     * @return bool|float
     */
    protected function isOrderEnabled($rate_buy, $rate_sell) {
        $rate = ($rate_buy + $rate_sell) / 2;
        $signal = Adviser::getInstance()->buySignal($rate);
        if (!$signal) {

            return false;
        }

        $bill = $this->bill->myBill('from');
        if ($bill < $this->getOrderMinAmount()) {
            if ($signal == 3) {
                echo "!!!-----> Signal 3, But not enough money! \n";
            }

            return false;
        }

        if ($signal == 1 && $this->haveThisRateOrders($rate_buy)){

            return false;
        }

        echo "-----> Signal_type: {$signal} \n";

        if ($bill >= $this->getOrderMinAmount() * 2) {

            return $this->getOrderMinAmount();
        } elseif ($signal == 3) {

            return $bill;
        } else {

            return false;
        }

    }

    /**
     * Создаем ордер и делаем запись в БД
     * @param $sum
     * @param $rate
     * @return bool
     */
    protected function addOrder($sum, $rate) {
        if (!($rate > 0)) {

            return false;
        }
        $amount = $sum/$rate;
        $amount = sprintf("%01.5f", $amount);

        try {
            $params =array(
                'pair' => $this->bill->getPair(),
                'type' => 'buy',
                'rate' => $rate,
                'amount' => $amount,
            );
            if (!$this->virtual) {
                $trade = $this->API->apiQuery('Trade', $params);
                $trade = $trade['return'];
            } else {
                $trade['order_id'] = 0;
            }
            $this->bill->createVirtualTransaction($amount, $rate);

            if ($trade['order_id'] == 0) {
                $status = "status = 'opened',";
            } else {
                $status = "";
            }

            $sql = "
                INSERT INTO
                    {$this->table}
                SET
                    order_id = '{$trade['order_id']}',
                    pair = '". $this->bill->getPair() . "',
                    rate = '{$rate}',
                    amount = '{$amount}',
                    {$status}
                    updated = NOW()
                ";

            $this->BD->query($sql);

            return true;
        } catch(BTCeAPIException $e) {
            echo $e->getMessage();
            echo __FUNCTION__ . " \n";

            return false;
        }

    }

    /**
     * Продаем для фиксации прибыли
     * @param $sum
     * @param $rate
     * @return bool
     */
    protected function closeOrder($id, $sum, $rate, $rate_buy) {
        if (!($rate > 0)) {

            return false;
        }
        $amount = $sum * (1 - $this->config->get('COMISSION'));
        $amount = sprintf("%01.5f", $amount);

        try {
            $params =array(
                'pair' => $this->bill->getPair(),
                'type' => 'sell',
                'rate' => $rate,
                'amount' => $amount,
            );
            if (!$this->virtual) {
                $trade = $this->API->apiQuery('Trade', $params);
                $trade = $trade['return'];
            } else {
                $trade['order_id'] = 0;
            }
            $profit  = MyBill::countRealProfit($sum, $rate_buy, $rate);
            $this->bill->createVirtualTransaction($amount, $rate, "sell", $profit);

            if ($trade['order_id'] == 0) {
                $status = "status = 'closed',";
            } else {
                $status = "";
            }

            $sql = "
                UPDATE
                    {$this->table}
                SET
                    closer_id = '{$trade['order_id']}',
                    {$status}
                    updated = NOW(),
                    profit = '{$profit}'
                WHERE
                    id = '{$id}'
                ";

            $this->BD->query($sql);

            return true;
        } catch (BTCeAPIException $e) {
            echo $e->getMessage();
            echo __FUNCTION__ . " \n";

            return false;
}
    }

    /**
     * @param $id
     * @return bool
     */
    protected function oneOrderCancel($id) {
        try {
            $this->API->apiQuery('CancelOrder', array('order_id' => $id));

            if ($id > 0) {
                $sql = "
                DELETE
                    {$this->table}
                WHERE
                    order_id = '{$id}'
                ";

                $this->BD->query($sql);
            }

            return true;
        } catch (BTCeAPIException $e) {
            echo $e->getMessage();
            echo "oneOrderCancel \n";

            return false;
        }
    }

    /**
     * @param $type
     * @param forClose = false - если собираемся искать для закрытия, то ставим true
     * @return bool|mysqli_result
     */
    protected function getMyActiveOrders($type, $forClose = false) {
        if (!$forClose) {
            $dop = "";
        } else {
            $dop = "AND closer_id > 0";
        }
        $sql = "
            SELECT
                id, order_id, amount, rate, closer_id
            FROM
                {$this->table}
            WHERE
                 pair = '". $this->bill->getPair() ."'
                 AND status = '{$type}'
                 {$dop}
        ";
        return $this->BD->query($sql);
    }

    /**
     * @param $new
     * @return bool|mysqli_result
     */
    protected function updateNew($new) {
        $new = array_keys($new);
        $ids = implode(",",$new);
        $sql = "
            UPDATE
                {$this->table}
            SET
                status = 'opened',
                updated = NOW()
            WHERE
                order_id IN ({$ids})
        ";
        return $this->BD->query($sql);
    }

    /**
     * @param $opened
     * @return bool|mysqli_result
     */
    protected function updateOpened($opened) {
        $opened = array_keys($opened);
        $ids = implode(",", $opened);
        $sql = "
            UPDATE
                {$this->table}
            SET
                status = 'closed',
                updated = NOW()
            WHERE
                closer_id IN ({$ids})
        ";
        return $this->BD->query($sql);
    }

    /**
     * Проверим наличие ордеров в данном коридоре курса
     * @param $rate
     * @return bool
     */
    public function haveThisRateOrders($rate) {
        $rate_min = $rate * (1 - $this->config->get('RATE_DIFF'));
        $rate_max = $rate * (1 + $this->config->get('RATE_DIFF'));

        $sql = "
            SELECT COUNT(id) cnt FROM
                {$this->table}
            WHERE
                pair = '". $this->bill->getPair() ."'
                AND status <> 'closed'
                AND rate BETWEEN {$rate_min} AND {$rate_max}
        ";
        $res = $this->BD->query($sql);
        $res = mysqli_fetch_array($res);
        if ($res['cnt'] > 0) {

            return true;
        }

        return false;
    }

}