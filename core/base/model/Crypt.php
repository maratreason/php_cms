<?php

namespace core\base\model;

use core\base\controller\Singleton;

class Crypt
{
    use Singleton;

    // Метод шифрования
    private $cryptMethod = 'AES-128-CBC';
    // Алгоритм хэширования
    private $hashAlgoritm = 'sha256';
    // Длина хэша для алгоритма хэширования. В данном случае 32 символа
    private $hashLength = 32;

    /**
     * Алгоритм шифрования
     * $cipherText_comp = "112233445566778899"
     * $iv_comb = "abcdefg
     * $hmac_comb = "0000000000"
     * $result = "1122a33b445c50000000000667d78899ef"
     */
    public function encrypt($str)
    {
        // длина алгоритма для псевдослучайной последовательности
        $ivlen = openssl_cipher_iv_length($this->cryptMethod);
        // генерируем вектор шифрования
        $iv = openssl_random_pseudo_bytes($ivlen);
        // шифруем данные
        $cipherText = openssl_encrypt($str, $this->cryptMethod, CRYPT_KEY, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac($this->hashAlgoritm, $cipherText, CRYPT_KEY, true);

        return $this->cryptCombine($cipherText, $iv, $hmac);
    }

    public function decrypt($str)
    {
        $ivlen = openssl_cipher_iv_length($this->cryptMethod);
        $crypt_data = $this->cryptUncombine($str, $ivlen);
        $original_plaintext = openssl_decrypt($crypt_data['str'], $this->cryptMethod, CRYPT_KEY, OPENSSL_RAW_DATA, $crypt_data['iv']);
        $calcmac = hash_hmac($this->hashAlgoritm, $crypt_data['str'], CRYPT_KEY, true);

        // сравнение, не подверженное атаке по времени
        if (hash_equals($crypt_data['hmac'], $calcmac)) return $original_plaintext;

        return false;
    }

    // Логика алгоритма шифрования
    protected function cryptCombine($str, $iv, $hmac)
    {
        $new_str = '';
        $str_len = strlen($str);
        $counter = (int) ceil(strlen(CRYPT_KEY) / ($str_len + $this->hashLength));
        $progress = 1;

        if ($counter >= $str_len) $counter = 1;

        for ($i = 0; $i < $str_len; $i++) {

            if ($counter < $str_len) {
                if ($counter === $i) {
                    $new_str .= substr($iv, $progress - 1, 1);
                    $progress++;
                    $counter += $progress;
                }
            } else {
                break;
            }

            $new_str .= substr($str, $i, 1);

        }

        $new_str .= substr($str, $i);
        $new_str .= substr($iv, $progress - 1);

        $new_str_half = (int) ceil(strlen($new_str) / 2);
        $new_str = substr($new_str, 0, $new_str_half) . $hmac . substr($new_str, $new_str_half);

        return base64_encode($new_str);
    }

    protected function cryptUncombine($str, $ivlen)
    {
        $crypt_data = [];
        $str = base64_decode($str);
        // получаем позицию, в которую вставлена строка в хеш
        $hash_position = (int) ceil((strlen($str) / 2) - ($this->hashLength / 2));
        // изымаем хэш
        $crypt_data['hmac'] = substr($str, $hash_position, $this->hashLength);
        $str = str_replace($crypt_data['hmac'], '', $str);
        $counter = (int) ceil(strlen(CRYPT_KEY) / (strlen($str) - $ivlen + $this->hashLength));
        $progress = 2;

        $crypt_data['str'] = '';
        $crypt_data['iv'] = '';

        for ($i = 0; $i < strlen($str); $i++) {

            if ($ivlen + strlen($crypt_data['str']) < strlen($str)) {
                if ($i === $counter) {
                    $crypt_data['iv'] .= substr($str, $counter, 1);
                    $progress++;
                    $counter += $progress;
                } else {
                    $crypt_data['str'] .= substr($str, $i, 1);
                }
            } else {
                $crypt_data_len = strlen($crypt_data['str']);
                $crypt_data['str'] .= substr($str, $i, strlen($str) - $ivlen - $crypt_data_len);
                $crypt_data['iv'] .= substr($str, $i + (strlen($str) - $ivlen - $crypt_data_len));

                break;
            }
        }

        return $crypt_data;
    }
}
