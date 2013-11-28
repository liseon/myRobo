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

    protected $pair;

    protected $BD;

    protected $config;

    protected $currency_from;

    protected $currency_to;

    protected function __construct() {
        $SECRET = include_once('.configuration/secret.config.php');
        $this->API = new BTCeAPI(
            $SECRET['KEY'],
            $SECRET['SECRET']
        );

        $this->BD = new mysqli($SECRET['BD_HOST'], $SECRET['BD_USER'], $SECRET['BD_PW'], $SECRET['BD_NAME'])
        or die("Error " . mysqli_error($this->bd));

        $this->config = include_once('.configuration/trader.config.php');
    }

    public function setPair($pair) {
        $this->pair = $pair;
        $curr = explode("_", $this->pair);
        $this->currency_from = $curr[1];
        $this->currency_to = $curr[0];
    }

    public function getOrderMinAmount() {

        return $this->config['ORDER_MIN_AMOUNT'][$this->currency_from];
    }


    /**
     * Вернет кол-во свободных денег
     * @return int
     */
    public function myBill() {
        $info = $this->getInfo();

        return $info['funds'];
    }

    /**
     * @return bool|array
     */
    protected function getInfo() {
        try {
            $getInfo = $this->API->apiQuery('getInfo');

            return $getInfo['return'];
        } catch (BTCeAPIException $e) {
            echo $e->getMessage();
            echo "getInfo \n";

            return false;
        }
    }

    /**
     * Проверим, можно ли сделать ордер!
     * Нельзя, если курс слишком велик
     * Нельзя, если мало денег!
     * Нельзя, если уже есть открытые ордеры в коридоре курса
     * Если денег у нас на 2 и более ордеров, то работаем по стандартной ставке
     * Если Денег меньше, чем на 2 ставки, то поставим сразу все!
     * @param $rate
     * @return bool|float
     */
    protected function isOrderEnabled($rate, $avg) {
        if ($rate > $avg * (1 + $this->config['RATE_UP_AVG'])) {

            return false;
        }

        $bill = $this->myBill();
        $bill = $bill[$this->currency_from];
        if ($bill < $this->getOrderMinAmount()) {

            return false;
        }
        $rate_min = $rate * (1 - $this->config['RATE_DIFF']);
        $rate_max = $rate * (1 + $this->config['RATE_DIFF']);

        $sql = "
            SELECT COUNT(id) cnt FROM
                orders
            WHERE
                pair = '". $this->pair ."'
                AND status <> 'closed'
                AND rate BETWEEN {$rate_min} AND {$rate_max}
        ";
        $res = $this->BD->query($sql);
        $res = mysqli_fetch_array($res);
        if ($res['cnt'] > 0) {

            return false;
        }

        if ($bill >= $this->getOrderMinAmount() * 2) {

            return $this->getOrderMinAmount();
        } else {

            return $bill;
        }

    }

    /**
     * Создаем ордер и делаем запись в БД
     * @param $sum
     * @param $rate
     * @return bool
     */
    protected function addOrder($sum, $rate) {
        $amount = $sum/$rate;
        $amount = sprintf("%01.5f", $amount);
        try {
            $params =array(
                'pair' => $this->pair,
                'type' => 'buy',
                'rate' => $rate,
                'amount' => $amount,
            );
            $trade = $this->API->apiQuery('Trade', $params);
            $trade = $trade['return'];

            echo "Buy sum: {$sum} rate: {$rate} \n";

            $sql = "
                INSERT INTO
                    orders
                SET
                    order_id = '{$trade['order_id']}',
                    pair = '{$this->pair}',
                    rate = '{$rate}',
                    amount = '{$amount}',
                    updated = NOW()
                ";

            $this->BD->query($sql);

            return true;
        } catch(BTCeAPIException $e) {
            echo $e->getMessage();
            echo "addOrder \n";

            return false;
        }
    }

    /**
     * Продаем для фиксации прибыли
     * @param $sum
     * @param $rate
     * @return bool
     */
    protected function closeOrder($id, $sum, $rate) {
        $amount = $sum * (1 - $this->config['COMISSION']);
        $amount = sprintf("%01.5f", $amount);
        try {
            $params =array(
                'pair' => $this->pair,
                'type' => 'sell',
                'rate' => $rate,
                'amount' => $amount,
            );
            $trade = $this->API->apiQuery('Trade', $params);
            $trade = $trade['return'];

            $sql = "
                UPDATE
                    orders
                SET
                    closer_id = '{$trade['order_id']}',
                    updated = NOW()
                WHERE
                    order_id = '{$id}'
                ";

            $this->BD->query($sql);

            return true;
        } catch(BTCeAPIException $e) {
            echo $e->getMessage();
            echo "closeOrder \n";

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

            $sql = "
                DELETE
                    orders
                WHERE
                    order_id = '{$id}'
                ";

            $this->BD->query($sql);

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
                order_id, amount, rate, closer_id
            FROM
                orders
            WHERE
                 pair = '". $this->pair ."'
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
                orders
            SET
                status = 'new',
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
                orders
            SET
                status = 'opened',
                updated = NOW()
            WHERE
                closer_id IN ({$ids})
        ";
        return $this->BD->query($sql);
    }


    protected function countProfit($rate1, $rate2) {
        $buy = 1 - $this->config['COMISSION'];
        $pull = ($buy * $rate2) * $buy;

        return ($pull - $rate1) / $rate1;
    }


}