<?php

class Adviser
{
    protected $trend = 'up';

    protected $local_min = 100000000;

    protected $local_max = 0;

    protected $values = array();

    protected $advLine = array();

    protected $adv2Line = array();

    private function __construct(){
    }

    public static function getInstance() {
        static $self = null;
        if (is_null($self)) {
            $self = new self();
        }

        return $self;
    }

    public function echoTrend() {
        echo "TREND: {$this->trend} MIN: {$this->local_min} MAX: {$this->local_max} \n";
    }

    /**
     * @param $rate
     * @return bool
     */
    private function setTrend($rate) {
        $return = ConfigHelper::getInstance()->get('RETURN_LVL');

        if ($this->trend == 'up') {
            if ($rate > $this->local_max) {
                $this->local_max = $rate;
            }  elseif ($rate < $this->local_max * (1 - $return)) {
                $this->trend = 'down';
                $this->local_min = $rate;

                return "sell";
            }
        } else {
            if ($rate < $this->local_min) {
                $this->local_min = $rate;
            }  elseif ($rate > $this->local_min * (1 + $return)) {
                $this->trend = 'up';
                $this->local_max = $rate;

                return "buy";
            }
        }

        return false;
    }

    /**
     * Установит текущий курс в историю + вернет текущее значение основной средней линии
     * @param $rate
     * @return float|int
     */
    private function setAdvLine($rate) {
        $this->values[] = $rate;
        $adv = 0;
        $kol = count($this->values);
        if ($kol >= ConfigHelper::getInstance()->get('VALUES') + 1) {
            array_shift($this->values);
            $adv = 0;
            $adv2 = 0;
            foreach ($this->values as $k => $val) {
                $adv += $val;
                if (
                    $k >= ConfigHelper::getInstance()->get('VALUES') - ConfigHelper::getInstance()->get('VALUES2') - 1
                ) {
                    $adv2 += $val;
                }
            }
            $adv = $adv / $kol;
            $adv2 = $adv / ConfigHelper::getInstance()->get('VALUES2');
            $this->advLine[] = $adv;
            $this->adv2Line[] = $adv2;
            if (count($this->advLine) >= 3) {
                array_shift($this->advLine);
                array_shift($this->adv2Line);
            }
        }

        return $adv;
    }

    /**
     * @param $rate
     * @return bool
     */
    private function isAdvBroken($rate) {
        $kol_adv = count($this->advLine);
        $kol_vals = count($this->values);
        if (!($kol_vals == ConfigHelper::getInstance()->get('VALUES') && $kol_adv == 2)){

            return false;
        }

        if (!(
            $this->adv2Line[$kol_adv - 1] >= $this->advLine[$kol_adv - 1]
            && $this->adv2Line[$kol_adv - 2] < $this->advLine[$kol_adv - 2]
        )) {

            return false;
        }
        //Все проверки пройдены. Сюда попадаем только если действительно мы пробили среднюю линию!
        $angle = Geometric::calcAngle(
            array($this->adv2Line[$kol_adv - 2], $this->adv2Line[$kol_adv - 1]),
            array($this->advLine[$kol_adv - 2], $this->advLine[$kol_adv - 1])
        );

        echo "Angle = {$angle} \n";

        if ($angle >= ConfigHelper::getInstance()->get('ANGLE')) {

            return true;
        }

        return false;
    }

    /**
     * Возвращает сигнал для покупки!
     * @param $rate
     * @return int
     */
    public function buySignal($rate) {
        $signal = 0;
        $trend = $this->setTrend($rate);

        $adv = $this->setAdvLine($rate);
        //Если мы ПОД средней линией + сильно ушли от локального максимума, то можно купить, цена хорошая!
        if (
            $rate < $adv
            && $rate < $this->local_max * (1 - ConfigHelper::getInstance()->get('CAN_BUY_LVL'))
        ) {
            $signal = 1;
        }

        //при развороте можно купить
        if ($trend == "buy") {
            $signal = 2;
        }

        //Если наша вспомогательная средняя линия тренда пробивает снизу вверх основную линию тренда под тупым углом,
        //то считаем данный признак мощным сигналом к росту!
        if ($this->isAdvBroken($rate)) {
            $signal = 3;
        }

        return $signal;
    }

    /**
     * Сигнал к продаже - разворот
     * @param $rate
     * @return int
     */
    public function sellSignal($rate) {
        $signal = 0;
        if ($this->setTrend($rate) == "sell") {
            $signal = 1;
        }

        return $signal;
    }

}