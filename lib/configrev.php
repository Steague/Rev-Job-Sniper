<?php

class ConfigRev
{
    private static $_instance = null;
    private $_db             = null;
    private $_config          = null;

    private function __construct()
    {
        //
        $this->_db = new PDO('sqlite:config.sqlite3');
        $this->_db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->_db->exec("CREATE TABLE IF NOT EXISTS config (key STRING, value STRING)");

        $this->_config = include('config.php');

        $insert = "INSERT INTO config (key, value) VALUES(:key, :value)";
        $stmt = $this->_db->prepare($insert);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);

        foreach ($this->_config as $k => $v)
        {
            $key = $k;
            $value = $v;

            $stmt->execute();
        }
    }

    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    public function printConfig()
    {
        $db = $this->_db;

        $result = $db->query("SELECT * FROM config");

        foreach($result as $row)
        {
            echo "key: " . $row['key'] . "\n";
            echo "value: " . $row['value'] . "\n";
            echo "\n";
        }
    }

    public function get($k)
    {
        $db = $this->_db;

        $stmt = $db->prepare("SELECT * FROM config WHERE key = ?");
        if (!$stmt->execute(array($k)))
        {
            return null;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result["value"];
    }

    public function set($k, $v)
    {
        $db = $this->_db;

        $get = $this->get($k);
        if ($get !== null &&
            $get !== false)
        {
            $stmt = $db->prepare("UPDATE config SET value = ? WHERE key = ?");
        }
        else
        {
            $stmt = $db->prepare("INSERT INTO config (value, key) VALUES(?, ?)");
        }
        if (!$stmt->execute(array($v, $k)))
        {
            return false;
        }

        return $stmt->execute();
    }

    public function __get($k)
    {
        return $this->get($k);
    }

    public function __set($k, $v)
    {
        return $this->set($k, $v);
    }
}