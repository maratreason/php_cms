<?php

namespace core\base\controller;

use ReflectionClass;

trait BaseMethods
{

    protected function clearStr($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $item) $str[$key] = $this->clearStr($item);

            return $str;
        } else {
            return trim(strip_tags($str));
        }
    }

    protected function clearNum(mixed $num)
    {
        return (!empty($num) && preg_match('/\d/', $num)) ? preg_replace('/[^\d.]/', '', $num) * 1 : 0;
    }

    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    protected function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    protected function redirect($http = false, $code = false)
    {
        if ($code) {
            $codes = ['301' => 'HTTP/1.1 301 Move Permanently'];

            if ($codes[$code]) {
                header($codes[$code]);
            }
        }

        if ($http) {
            $redirect = $http;
        } else {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : PATH;
        }

        header("Location: $redirect");

        exit;
    }

    protected function getStyles()
    {
        if ($this->styles) {
            foreach ($this->styles as $style) echo '<link rel="stylesheet" href="' . $style . '" />';
        }
    }

    protected function getScripts()
    {
        if ($this->scripts) {
            foreach ($this->scripts as $script) echo '<script src="' . $script . '" ></script>';
        }
    }

    protected function writeLog($message, $file = 'log.txt', $event = 'Fault')
    {
        $dateTime = new \DateTime();
        $str = $event . ': ' . $dateTime->format('d-m-Y H:i:s') . ' - ' . $message . "\r\n";

        file_put_contents('log/' . $file, $str, FILE_APPEND);
    }

    protected function getController()
    {
        return $this->controller 
            ?: $this->controller = preg_split('/_?controller/', strtolower(
                preg_replace('/([^A-Z])([A-Z])/', '$1_$2', (new ReflectionClass($this))->getShortName())),
                0,
                PREG_SPLIT_NO_EMPTY
            )[0];
    }

    /**
     * Форматирование даты для блока новостей
     *
     * @param string $date
     * @return void
     */
    protected function dateFormat($date)
    {
        if (!$date) {
            return $date;
        }

        $daysArr = [
            'Sunday' => 'Воскресенье',
            'Monday' => 'Понедельник',
            'Tuesday' => 'Вторник',
            'Wednesday' => 'Среда',
            'Thursday' => 'Четверг',
            'Friday' => 'Пятница',
            'Saturday' => 'Суббота'
        ];

        $monthsArr = [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];

        $dateArr = [];
        $dateData = new \DateTime($date);

        $dateArr['year'] = $dateData->format('Y');
        $dateArr['month'] = $monthsArr[$this->clearNum($dateData->format('m'))];
        $dateArr['monthFormat'] = preg_match('/т$/u', $dateArr['month']) ? $dateArr['month'] . 'а' : preg_replace('/[ьй]/u', 'я', $dateArr['month']);
        $dateArr['weekday'] = $daysArr[$dateData->format('l')];
        $dateArr['day'] = $dateData->format('d');
        $dateArr['time'] = $dateData->format('H:i:s');

        $dateArr['format'] = mb_strtolower($dateArr['day'] . ' ' . $dateArr['monthFormat'] . ' ' . $dateArr['year']);

        return $dateArr;
    }
}
