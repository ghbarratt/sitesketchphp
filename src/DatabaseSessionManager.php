<?php

declare(strict_types=1);

namespace Sitesketch;

use PDOException;

class DatabaseSessionManager
{
    private static $dbh;
    private static $sessionCode;
    private static $syncRealSession;
    private static $data;
    private static $errors = [];
    private static $tableName = 'session';


    public function __construct($dbh, $sessionCode = false, $syncRealSession = true)
    {
        self::$syncRealSession = $syncRealSession;

        if (!isset(self::$dbh) || !is_object(self::$dbh)) {
            if (isset($dbh)) {
                self::$dbh = $dbh;
            } else {
                self::$errors[] = 'Database object is not set';
                return false;
            }
        }

        if (self::$syncRealSession) {
            $result = session_start();
        } else {
            $result = true;
        }

        if ($sessionCode) {
            self::setSessionCode($sessionCode);
        } elseif (!self::$sessionCode && self::$syncRealSession) {
            self::$sessionCode = session_id();
        } elseif (!self::$sessionCode && isset($_COOKIE['PHPSESSID'])) {
            self::$sessionCode = $_COOKIE['PHPSESSID'];
        }

        if (!self::$sessionCode) {
            self::$errors[] = 'Unable to get session code (PHPSESSID) from browser (cookies). Are cookies turned off?';
            return false;
        }

        return $result;
    }

    public static function getSessionCode($sessionId = null, $dbhIn = null)
    {
        if (!$dbhIn) {
            if (isset(self::$dbh)) {
                $dbhIn = self::$dbh;
            }
        }
        if (!empty($sessionId) && is_numeric($sessionId)) {
            $sql = "SELECT code FROM " . self::$tableName . " WHERE id = " . (int)$sessionId;
            try {
                $sth = $dbhIn->prepare($sql);
                $sth->execute();
                $result = $sth->fetch();
                return $result['code'];
            } catch (PDOException $e) {
                self::$errors[] = $e->getMessage();
                return false;
            }
        } else {
            return self::$sessionCode;
        }
    }

    public static function setSessionCode($sessionCode = false)
    {
        if (self::$syncRealSession) {
            if (!$sessionCode) {
                $result = session_regenerate_id(true);
                if ($result) {
                    $sessionCode = session_id();
                }
                $result = true;
            } else {
                $result = session_id($sessionCode);
            }
        } else {
            $result = true;
        }

        setcookie('PHPSESSID', $sessionCode, 0, '/'); // important to set path to /
        self::$sessionCode = $sessionCode;

        return $result;
    }

    public static function sessionExistsWithCode($sessionCode = false, $dbhIn = false)
    {
        if (!$dbhIn) {
            if (isset(self::$dbh)) {
                $dbhIn = self::$dbh;
            }
        }

        if (!$sessionCode && isset(self::$sessionCode) && self::$sessionCode) {
            $sessionCode = self::$sessionCode;
        }

        $sql =
        "	
			SELECT code FROM " . self::$tableName . " WHERE code = '" . $sessionCode . "'
		";
        try {
            $sth = $dbhIn->prepare($sql);
            $sth->execute();
            $result = $sth->fetch();
            return $result['code'];
        } catch (PDOException $e) {
            self::$errors[] = $e->getMessage();
            return false;
        }
    }

    public function renewData()
    {
        self::$data = self::getData(false, true);
    }

    public function getData($sessionCode = false, $forceQuery = false)
    {
        if (!$sessionCode && self::$sessionCode) {
            $sessionCode = self::$sessionCode;
        }
        if (
            !$forceQuery && $sessionCode == self::$sessionCode
            && isset(self::$data)
            && is_array(self::$data)
            && count(self::$data)
        ) {
            return self::$data;
        }

        $sql =
        "	
			SELECT data
			FROM " . self::$tableName . "
			WHERE code = '" . $sessionCode . "'
		";

        try {
            $sth = self::$dbh->prepare($sql);
            $sth->execute();
            $result = $sth->fetch();
            $dataString = $result['data'];
        } catch (PDOException $e) {
            self::$errors[] = $e->getMessage();
            return false;
        }

        $data = unserialize($dataString);

        if (self::$syncRealSession) {
            if (is_array($data)) {
                $data = array_merge($_SESSION, $data);
            } else {
                $data = $_SESSION;
            }
        }

        if ($sessionCode == self::$sessionCode) {
            self::$data = $data;
        }
        $data['session_code'] = $sessionCode;

        return $data;
    }

    public function get($key)
    {
        if ((!isset(self::$data) || !is_array(self::$data)) && isset(self::$sessionCode)) {
            self::$data = self::getData();
        }

        if (isset(self::$data[$key])) {
            return self::$data[$key];
        }

        return false;
    }

    public function remove($key, $writeToDatabase = true)
    {
        unset(self::$data[$key]);

        $sql = 'SELECT id FROM ' . self::$tableName . " WHERE code = " . self::$dbh->quote(self::$sessionCode);
        try {
            $sth = self::$dbh->prepare($sql);
            $sth->execute();
            $result = $sth->fetch();
            $sessionId = $result['id'];
        } catch (PDOException $e) {
            self::$errors[] = $e->getMessage();
            return false;
        }


        $lastIp = $_SERVER['REMOTE_ADDR'];

        if (self::$syncRealSession) {
            unset($_SESSION[$key]);
        }

        if ($writeToDatabase) {
            if ($sessionId) {
                $sql =
                "
					UPDATE " . self::$tableName . "
					SET 
						data = " . self::$dbh->quote(serialize(self::$data)) . ",
						updated = NOW(),
						last_ip = " . self::$dbh->quote($lastIp) . "
					WHERE id = " . self::$dbh->quote($sessionId) . "
				";
                try {
                    $sth = self::$dbh->prepare($sql);
                    $sth->execute();
                } catch (PDOException $e) {
                    self::$errors[] = $e->getMessage() . ' - SQL: ' . $sql;
                    return false;
                }
                return true;
            }
        }
        return false;
    }

    public function deleteSessionFromDatabase($sessionId, $dbhIn = null)
    {
        if (!$dbhIn) {
            if (isset(self::$dbh)) {
                $dbhIn = self::$dbh;
            }
        }

        $sql = 'SELECT id FROM ' . self::$tableName . ' WHERE id = ' . (int)$sessionId;
        try {
            $sth = $dbhIn->prepare($sql);
            $sth->execute();
            $result = $sth->fetch();
            $foundId = $result['id'];
        } catch (PDOException $e) {
            self::$errors[] = $e->getMessage();
            return false;
        }

        if ($sessionId == $foundId) {
            $sql = 'DELETE FROM ' . self::$tableName . ' WHERE id = ' . (int)$sessionId;
            try {
                $sth = $dbhIn->prepare($sql);
                $sth->execute();
            } catch (PDOException $e) {
                self::$errors[] = $e->getMessage() . ' - SQL: ' . $sql;
                return false;
            }
            return true;
        }
        return false;
    }

    public function set($key, $value, $writeToDatabase = true)
    {

        self::$data[$key] = $value;

        $sql = 'SELECT id FROM ' . self::$tableName . " WHERE code = " . self::$dbh->quote(self::$sessionCode);

        try {
            $sth = self::$dbh->prepare($sql);
            $sth->execute();
            $result = $sth->fetch();
            $sessionId = $result['id'];
        } catch (PDOException $e) {
            self::$errors[] = $e->getMessage();
            return false;
        }

        $lastIp = $_SERVER['REMOTE_ADDR'];

        if (self::$syncRealSession) {
            $_SESSION[$key] = $value;
        }

        if ($writeToDatabase) {
            if ($sessionId) {
                $sql =
                "
					UPDATE " . self::$tableName . "
					SET 
						data = " . self::$dbh->quote(serialize(self::$data)) . ",
						updated = NOW(),
						last_ip = " . self::$dbh->quote($lastIp) . "
					WHERE id = " . self::$dbh->quote($sessionId) . "
				";
            } else {
                // no match
                $sql = 'INSERT INTO ' . self::$tableName . '
					(code, data, last_ip, created)
					VALUES
					(' . self::$dbh->quote(self::$sessionCode) . ', ' . self::$dbh->quote(serialize(self::$data))
                    . ', ' . self::$dbh->quote($lastIp) . ', NOW())';
            }

            try {
                $sth = self::$dbh->prepare($sql);
                $sth->execute();
            } catch (PDOException $e) {
                self::$errors[] = $e->getMessage() . ' - SQL: ' . $sql;
                return false;
            }
        }

        return true;
    }

    public function getErrors()
    {
        return self::$errors;
    }
}
