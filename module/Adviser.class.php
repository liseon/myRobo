<?php

class Adviser
{
    protected $trend = 'up';

    protected $local_min = 100000000;

    protected $local_max = 0;

    protected $values = array();

    protected $advLine = array();

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
     * Установит текущий курс в историю + вернет текущее значение средней линии
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
            foreach ($this->values as $val) {
                $adv += $val;
            }
            $adv = $adv / $kol;
            $this->advLine[] = $adv;
            if (count($this->advLine) >= 3) {
                array_shift($this->advLine);
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
            $this->values[$kol_vals - 1] >= $this->advLine[$kol_adv - 1]
            && $this->values[$kol_vals - 2] < $this->advLine[$kol_adv - 1]
        )) {

            return false;
        }
        //Все проверки пройдены. Сюда попадаем только если действительно мы пробили среднюю линию!
        $angle = Geometric::calcAngle(
            array($this->values[$kol_vals - 2], $this->values[$kol_vals - 1]),
            array($this->advLine[$kol_adv - 2], $this->advLine[$kol_adv - 1])
        );

        if ($angle >= ConfigHelper::getInstance()->get('ANGLE')) {

            return true;
        }

        return false;
    }

    /**
     * @param $rate
     * @return int
     */
    public function buySignal($rate) {
        $signal = 0;
        //при развороте можно купить
        if ($this->setTrend($rate) == "buy") {
            $signal = 2;
        }

        $adv = $this->setAdvLine($rate);
        //Если мы ПОД средней линией + сильно ушли от локального максимума, то можно купить, цена хорошая!
        if (
            $rate < $adv
            && $rate < $this->local_max * (1 - ConfigHelper::getInstance()->get('CAN_BUY_LVL'))
        ) {
            $signal = 1;
        }




        return $signal;

    }

}