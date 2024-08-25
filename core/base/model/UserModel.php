<?php

namespace core\base\model;

use core\base\controller\BaseMethods;
use core\base\controller\Singleton;
use core\base\exceptions\AuthException;

class UserModel extends BaseModel
{
    use Singleton;
    use BaseMethods;

    private $cookieName = 'identifier';
    private $cookieAdminName = 'identifier';
    private $userData = [];
    private $error;
    // Таблица посетителей
    private $userTable = 'visitors';
    // Таблица пользователей и админов
    private $adminTable = 'users';
    // Таблица за некорректные попытки входа пользователей
    private $blockedTable = 'blocked_access';

    public function getAdminTable()
    {
        return $this->adminTable;
    }

    public function getBlockedTable()
    {
        return $this->blockedTable;
    }

    public function getLastError()
    {
        return $this->error;
    }

    public function setAdmin()
    {
        $this->cookieName = $this->cookieAdminName;
        $this->userTable = $this->adminTable;

        if (!in_array($this->userTable, $this->showTables())) {
            $query = 'CREATE TABLE ' . $this->userTable . '
                (
                    id int auto_increment primary key,
                    name varchar(255) null,
                    login varchar(255) null,
                    password varchar(32) null,
                    credentials text null
                )
                charset = utf8
            ';

            if (!$this->query($query, 'u')) {
                exit('Ошибка создания таблицы ' . $this->userTable);
            }

            $this->add($this->userTable, [
                'fields' => ['name' => 'admin', 'login' => 'admin', 'password' => md5('123')]
            ]);
        }

        if (!in_array($this->blockedTable, $this->showTables())) {
            $query = 'CREATE TABLE ' . $this->blockedTable . '
                (
                    id int auto_increment primary key,
                    login varchar(255) null,
                    ip varchar(32) null,
                    trying tinyint(1) null,
                    time datetime null
                )
                charset = utf8
            ';

            if (!$this->query($query, 'u')) {
                exit('Ошибка создания таблицы ' . $this->blockedTable);
            }
        }
    }

    public function checkUser($id = false, $admin = false)
    {
        $admin && $this->userTable !== $this->adminTable && $this->setAdmin();

        $method = 'unPackage';

        if ($id) {
            $this->userData['id'] = $id;
            $method = 'set';
        }

        try {
            $this->$method();
        } catch (AuthException $e) {
            $this->error = $e->getMessage();

            !empty($e->getCode()) && $this->writeLog($this->error, 'log_user.txt');

            return false;
        }

        return $this->userData;
    }
}
