<?php

declare(strict_types=1);

namespace Sitesketch;

use PDO;
use PDOException;
use Sitesketch\Traits\DatabaseHandlerTrait;

class UserManager
{
    use DatabaseHandlerTrait;

    protected $hashingPasswords = true;
    protected $hashMethod = 'sha256';

    protected $useSalt = true;
    protected $saltAlias = 'ap_salt';
    protected $saltLength = 8;
    protected $saltField = 'salt';
    protected $salt;

    protected $usersTable = 'users';
    protected $userRolesTable = 'user_roles';
    protected $idColumn = 'id';
    protected $passwordColumn = 'password_hash';
    protected $userRoleIdColumn = 'user_role_id';
    protected $emailColumn = 'email';
    protected $sessionKey = 'user_id';

    protected $errors = [];
    protected $messages = [];
    protected $warnings = [];

    protected $cb;

    protected $replacements = [];

    protected $userId;

    public function __construct(array|null $config = null, PDO|null $pdo = null)
    {
        $this->cb = new ContentBuilder();

        if (!isset($_SESSION)) {
            session_start();
        }

        // If passing in a PDO instance feels right to you, do that. Alternatively you can use the just-in-time ("lazy")
        // static singleton that comes with the DatabaseHandlerTrait
        if ($pdo !== null) {
            self::$pdo = $pdo;
        } elseif (!empty($config['pdo']) && is_object($config['pdo'])) {
            self::$pdo = $config['pdo'];
        } elseif (!empty($config['dbh']) && is_object($config['dbh'])) {
            self::$pdo = $config['dbh'];
        } else {
            self::$pdo = self::getPdo();
        }

        if (empty(self::$pdo)) {
            die('You must have a database connection established in order to use the User Manager.');
        }

        if (!empty($config['usersTable']) || !empty($config['users_table'])) {
            $this->usersTable = $config['usersTable'] ?? $config['users_table'];
        }
        if (!empty($config['userRolesTable']) || !empty($config['user_roles_table'])) {
            $this->userRolesTable = $config['userRolesTable'] ?? $config['user_roles_table'];
        }

        if (!empty($config['idColumn']) || !empty($config['id_column'])) {
            $this->idColumn = $config['idColumn'] ?? $config['id_column'];
        }
        if (!empty($config['emailColumn']) || !empty($config['email_column'])) {
            $this->emailColumn = $config['emailColumn'] ?? $config['email_column'];
        }
        if (!empty($config['passwordColumn']) || !empty($config['password_column'])) {
            $this->passwordColumn = $config['passwordColumn'] ?? $config['password_column'];
        }
        if (!empty($config['userRoleIdColumn']) || !empty($config['user_role_id_column'])) {
            $this->userRoleIdColumn = $config['userRoleIdColumn'] ?? $config['user_role_id_column'];
        }
    }

    public function getReplacements()
    {
        return array_merge($this->getDefaultReplacements(), $this->replacements);
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function addError($error)
    {
        $this->errors[] = $error;
    }

    public function addWarning($warning)
    {
        $this->warnings[] = $warning;
    }

    public function addReplacements($replacements)
    {
        if (!is_array($this->replacements)) {
            $this->replacements = [];
        }
        $this->replacements = array_merge($replacements, $this->replacements);
    }

    public function addReplacement($tag, $value)
    {
        $this->replacements = array_merge([$tag => $value], $this->replacements);
    }

    public function insertUser($userData)
    {
        if (!isset($userData)) {
            return false;
        }

        if ($this->hashingPasswords && isset($userData['password']) && strlen($userData['password']) != 64) {
            $plaintextPassword = $userData['password'];
            $userData['password_hash'] = hash($this->hashMethod, $plaintextPassword);
        }

        $sql =
        "
			INSERT INTO " . $this->usersTable . "
			(" . implode(',', array_keys($userData)) . ")
			VALUES
			('" . implode("','", $userData) . "')
		";

        try {
            $count = self::$pdo->exec($sql);
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error trying to insert user: ' . $e->getMessage() . "\n DEBUG sql: "
                . $sql;
        }

        if (!empty($count)) {
            return self::$pdo->lastInsertId();
        }

        return false;
    }

    public function addUser($userData)
    {
        return $this->createUser($userData);
    }

    public function createUser($userData)
    {
        $userId = $this->insertUser($userData);
        if ($userId) {
            $this->messages[] = 'Successfully created user account for ' . ($userData['name'] ?: $userData['email']);
        }
        return $userId;
    }

    public function getHashMethod()
    {
        return $this->hashMethod;
    }

    private function getRandomString($length = false)
    {
        if (!$length) {
            $length = $this->saltLength;
        }
        $result = md5(uniqid((string)rand(), true));
        if (is_numeric($length) && $length) {
            $result = substr($result, 0, (int)$length);
        }

        return $result;
    }

    public function getSaltAlias()
    {
        return $this->saltAlias;
    }

    public function renewSalt($email = false, $salt = false)
    {
        $newSalt = $this->getRandomString($this->saltLength);

        $_SESSION[$this->saltAlias] = $newSalt;
        $this->salt = $newSalt;

        return $newSalt;
    }

    public function isUsingSalt()
    {
        return $this->useSalt;
    }

    public function getSalt($userId = false)
    {
        if (isset($this->salt) && $this->salt) {
            return $this->salt;
        }

        if (isset($_SESSION) && isset($_SESSION[$this->saltAlias])) {
            return $_SESSION[$this->saltAlias];
        }

        return $this->renewSalt();
    }

    public function isUserEmail($email)
    {
        $sql =
        '
			SELECT ' . $this->idColumn . '
			FROM ' . $this->usersTable . '
			WHERE ' . $this->emailColumn . ' = ?
		';

        $sth = self::$pdo->prepare($sql);
        try {
            $sth->execute([$email]);
            return $sth->fetchColumn();
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
        }
        return false;
    }

    public function isUserValid($email, $password, $options = null)
    {
        if ($this->hashingPasswords) {
            if (!is_array($options) || !isset($options['prehashed']) || !$options['prehashed']) {
                $password = hash($this->hashMethod, $password);
            }
        }

        $sql =
        '
			SELECT ' . $this->passwordColumn . ', ' . $this->idColumn . '
			FROM ' . $this->usersTable . "
			WHERE " . $this->emailColumn . " = ?
		";

        $sth = self::$pdo->prepare($sql);
        try {
            $sth->execute([$email]);
            $userData = $sth->fetch();
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
            return false;
        }

        $userId = false;

        if ($password == hash($this->hashMethod, $userData['password_hash'] . $this->getSalt())) {
            $userId = $userData[$this->idColumn];
        }

        return $userId;
    }

    public function logIn($email, $password = null, $options = null)
    {
        if (!empty($options['skip_authentication'])) {
            $userId = $this->isUserEmail($email);
        } else {
            $userId = $this->isUserValid($email, $password, $options);
        }

        if ($userId) {
            $_SESSION[$this->sessionKey] = $userId;
            $this->userId = $userId;
            $this->messages[] = 'Successfully logged in as user with email ' . $email;
            return true;
        } else {
            print_r($this->errors);
        }
        return false;
    }

    public function logOut($options = null)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        unset($_SESSION[$this->sessionKey]);
    }

    public function getUserID()
    {
        if (isset($this->userId) && is_numeric($this->userId)) {
            return $this->userId;
        } elseif (
            isset($this->sessionKey)
            && isset($_SESSION[$this->sessionKey])
            && is_numeric($_SESSION[$this->sessionKey])
        ) {
            return $_SESSION[$this->sessionKey];
        }
    }

    public function getUserData($userId = null)
    {
        if (empty($userId)) {
            $userId = $this->getUserID();
        }

        $sql = 'SELECT u.*, ur.name AS role_name
			FROM 
				' . $this->usersTable . ' u
				LEFT JOIN ' . $this->userRolesTable . ' ur ON ur.' . $this->idColumn . ' = '
            . $this->userRoleIdColumn . '
			WHERE u.' . $this->idColumn . ' = ?
		';

        $sth = self::$pdo->prepare($sql);
        try {
            $sth->execute([$userId]);
            return $sth->fetch();
        } catch (PDOException $e) {
            echo 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
        }
        return false;
    }

    public function getLoggedInUserData()
    {
        $userId = $this->getLoggedInUserID();
        return $this->getUserData($userId);
    }

    public function getLoggedInUserID()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        if (isset($_SESSION[$this->sessionKey])) {
            return $_SESSION[$this->sessionKey];
        } else {
            return false;
        }
    }

    public function whoIsLoggedIn()
    {
        return $this->getLoggedInUserID();
    }

    public function bootAnonymous($url = false)
    {
        if (!$url) {
            $url = '/login/';
        }
        if ($userId = $this->whoIsLoggedIn()) {
            return $userId;
        } else {
            header('Location: ' . $url);
            exit;
        }
    }
}
