<?php

namespace libraries;

class FileEdit
{
    protected $imgArr = [];

    protected $directory;

    public function addFile($directory = false)
    {
        if (!$directory) {

            $this->directory = $_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR;

        } else {

            $this->directory = $directory;

        }

        foreach ($_FILES as $key => $file) {
            
            if (is_array($file['name'])) {
                
                $file_arr = [];

                foreach ($file['name'] as $i => $value) {

                    if (!empty($file['name'][$i])) {

                        $file_arr['name'] = $file['name'][$i];
                        $file_arr['type'] = $file['type'][$i];
                        $file_arr['tmp_name'] = $file['tmp_name'][$i];
                        $file_arr['error'] = $file['error'][$i];
                        $file_arr['size'] = $file['size'][$i];

                        $res_name = $this->createFile($file_arr);

                        if ($res_name) $this->imgArr[$key][] = $res_name;
                        // if ($res_name) $this->imgArr[$key][$i] = $res_name;

                    }

                }

            } else {

                if (isset($file['name'])) {

                    $res_name = $this->createFile($file);

                    if ($res_name) $this->imgArr[$key] = $res_name;

                }

            }

        }

        return $this->getFiles();

    }

    protected function createFile($file)
    {
        $fileNameArr = explode('.', $file['name']);

        $ext = $fileNameArr[count($fileNameArr) - 1];

        unset($fileNameArr[count($fileNameArr) - 1]);

        $fileName = implode('.', $fileNameArr);

        $fileName = (new \libraries\TextModify())->translit($fileName);

        $fileName = $this->checkFile($fileName, $ext);
        // Получаем файл с полным путём.
        $fileFullName = $this->directory . $fileName;
        // Перемещение файла.
        if ($this->uploadFile($file['tmp_name'], $fileFullName)) {

            return $fileName;

        }

        return false;
    }
    // $tmpName - где файл лежит сейчас, $destination - куда перемещаем.
    protected function uploadFile($tmpName, $destination)
    {
        if (move_uploaded_file($tmpName, $destination)) return true;

        return false;
    }
    // $fileLastName - динамически генерируется название если такой файл уже существует.
    protected function checkFile($fileName, $ext, $fileLastName = '')
    {
        // Если такого файла не существует, то возвращаем готовое имя файла
        if (!file_exists($this->directory . $fileName . $fileLastName . '.' . $ext)) {

            return $fileName . $fileLastName . '.' . $ext;

        }
        // Иначе дополняем название _, хэшом и датой с конкатенацией со случайным числом. Можно вместо hash использовать md5(). Но hash возвращает короткий хэш. Хэш 'crc32' один из самых коротких.
        return $this->checkFile($fileName, $ext, '_' . hash('crc32', time() . mt_rand(1, 1000)));
    }

    public function getFiles()
    {
        return $this->imgArr;
    }

}
