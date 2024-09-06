<?php

namespace core\user\helpers;

trait ValidationHelper
{
    protected function emptyField($value, $answer)
    {
        $value = $this->clearStr($value);

        if (empty($value)) {
            $this->sendError('Не заполнено поле ' . $answer);
        }

        return $value;
    }

    protected function numericField($value, $answer)
    {
        $value = preg_replace('/\D/', '', $value);

        !$value && $this->sendError('Некорректное поле ' . $answer);

        return $value;
    }

    protected function phoneField($value, $answer = null)
    {
        $value = preg_replace('/\D/', '', $value);
        
        if (strlen($value) === 11) {
            $value = preg_replace('/^8/', '7', $value);
        }

        return $value;
    }

    protected function emailField($value, $answer)
    {
        $value = $this->clearStr($value);

        if (!preg_match('/^[\w\-\.]+@[\w\-]+\.[\w\-]+/', $value)) {
            $this->sendError('Некорректный формат поля ' . $answer);
        }

        return $value;
    }

    protected function sendError($text, $class = 'error')
    {
        $_SESSION['res']['answer'] = '<div class="' . $class . '">' . $text . '</div>';

        if ($class === 'error') {
            $this->addSessionData();
        }
    }

    protected function sendSuccess($text, $class = 'success')
    {
        $this->sendError($text, $class);
    }
}
