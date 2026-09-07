<?php

declare(strict_types=1);

namespace Sitesketch;

use PDOException;

// User Manager class - Manages user account
//
// part of the Sitesketch Framework
// property of Adept Sites LLC
// developed by Glen H. Barratt


class UserManager
{
    protected $hashing_passwords = true;
    protected $hash_method = 'sha256';

    protected $use_salt = true;
    protected $salt_alias = 'ap_salt';
    protected $salt_length = 8;
    protected $salt_field = 'salt';

    protected $users_table = 'users';
    protected $user_roles_table = 'user_roles';
    protected $id_column = 'id';
    protected $password_column = 'password_hash';
    protected $user_role_id_column = 'user_role_id';
    protected $email_column = 'email';
    protected $session_key = 'user_id';

    protected $errors = [];
    protected $messages = [];
    protected $warnings = [];

    protected $dbh;
    protected $cb;

    protected $replacements = [];

    protected $user_id;


    public function __construct($config, $dbh = null)
    {
        $this->cb = new ContentBuilder();

        if (!isset($_SESSION)) {
            session_start();
        }

        if (!empty($config['dbh']) && is_object($config['dbh'])) {
            $this->dbh = $config['dbh'];
        } elseif (isset($dbh) && is_object($dbh)) {
            $this->dbh = $dbh;
        } else {
            die('You must have a database connection established in order to use the User Manager.');
        }

        if (!empty($config['users_table'])) {
            $this->users_table = $config['users_table'];
        }
        if (!empty($config['user_roles_table'])) {
            $this->user_roles_table = $config['user_roles_table'];
        }

        if (!empty($config['id_column'])) {
            $this->id_column = $config['id_column'];
        }
        if (!empty($config['email_column'])) {
            $this->email_column = $config['email_column'];
        }
        if (!empty($config['password_column'])) {
            $this->password_column = $config['password_column'];
        }
        if (!empty($config['user_role_id_column'])) {
            $this->user_role_id_column = $config['user_role_id_column'];
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

    public function insertUser($user_data)
    {

        if (!isset($user_data)) {
            return false;
        }

        if ($this->hashing_passwords && isset($user_data['password']) && strlen($user_data['password']) != 64) {
            $plaintext_password = $user_data['password'];
            $user_data['password_hash'] = hash($this->hash_method, $plaintext_password);
        }

        $sql =
        "
			INSERT INTO " . $this->users_table . "
			(" . implode(',', array_keys($user_data)) . ")
			VALUES
			('" . implode("','", $user_data) . "')
		";
        //echo 'DEBUG sql <pre>'.$sql.'</pre>';

        try {
            $count = $this->dbh->exec($sql);
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error trying to insert user: ' . $e->getMessage() . "\n DEBUG sql: "
                . $sql;
        }

        if (!empty($count)) {
            return $this->dbh->lastInsertId();
        }

        return false;
    }

    public function addUser($user_data)
    {
        return $this->createUser($user_data);
    }

    public function createUser($user_data)
    {
        $user_id = $this->insertUser($user_data);
        if ($user_id) {
            $this->messages[] = 'Successfully created user account for ' . ($user_data['name'] ?: $user_data['email']);
        }
        return $user_id;
    }

    public function getHashMethod()
    {
        return $this->hash_method;
    }

    private function getRandomString($length = false)
    {
        if (!$length) {
            $length = $this->salt_length;
        }
        $result = md5(uniqid(rand(), true));
        if (is_numeric($length) && $length) {
            $result = substr($result, 0, $length);
        }

        return $result;
    }

    public function getSaltAlias()
    {
        return $this->salt_alias;
    }

    public function renewSalt($email = false, $salt = false)
    {
        // Basically the salt is stored on the client and in the database.
        $new_salt = $this->getRandomString($this->salt_length);

        $_SESSION[$this->salt_alias] = $new_salt;
        $this->salt = $new_salt;

        return $new_salt;
    }

    public function isUsingSalt()
    {
        return $this->use_salt;
    }

    public function getSalt($user_id = false)
    {
        if (isset($this->salt) && $this->salt) {
            return $this->salt;
        }

        if (isset($_SESSION) && isset($_SESSION[$this->salt_alias])) {
            return $_SESSION[$this->salt_alias];
        }

        return $this->renewSalt();
    }

    public function isUserEmail($email)
    {
        $sql =
        '
			SELECT ' . $this->id_column . '
			FROM ' . $this->users_table . '
			WHERE ' . $this->email_column . ' = ?
		';

        $sth = $this->dbh->prepare($sql);
        try {
            $sth->execute([$email]);
            return $sth->fetchColumn();
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
        }
        return false;
    }

    public function isUserValid($email, $password, $options)
    {
        if ($this->hashing_passwords) {
            if (!is_array($options) || !isset($options['prehashed']) || !$options['prehashed']) {
                $password = hash($this->hash_method, $password);
            }
        }

        $sql =
        '
			SELECT ' . $this->password_column . ', ' . $this->id_column . '
			FROM ' . $this->users_table . "
			WHERE " . $this->email_column . " = ?
		";

        $sth = $this->dbh->prepare($sql);
        try {
            $sth->execute([$email]);
            $user_data = $sth->fetch();
        } catch (PDOException $e) {
            $this->errors[] = 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
            return false;
        }

        $user_id = false;

        if ($password == hash($this->hash_method, $user_data['password_hash'] . $this->getSalt())) {
            $user_id = $user_data[$this->id_column];
        }

        return $user_id;
    }

    public function logIn($email, $password = null, $options = null)
    {
        if (!empty($options['skip_authentication'])) {
            $user_id = $this->isUserEmail($email);
        } else {
            $user_id = $this->isUserValid($email, $password, $options);
        }

        if ($user_id) {
            $_SESSION[$this->session_key] = $user_id;
            $this->user_id = $user_id;
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

        unset($_SESSION[$this->session_key]);
    }

    public function getUserID()
    {
        if (isset($this->user_id) && is_numeric($this->user_id)) {
            return $this->user_id;
        } elseif (
            isset($this->session_key)
            && isset($_SESSION[$this->session_key])
            && is_numeric($_SESSION[$this->session_key])
        ) {
            return $_SESSION[$this->session_key];
        }
    }

    public function getUserData($user_id = null)
    {
        if (empty($user_id)) {
            $user_id = $this->getUserID();
        }

        $sql = 'SELECT u.*, ur.name AS role_name
			FROM 
				' . $this->users_table . ' u
				LEFT JOIN ' . $this->user_roles_table . ' ur ON ur.' . $this->id_column . ' = '
            . $this->user_role_id_column . '
			WHERE u.' . $this->id_column . ' = ?
		';

        $sth = $this->dbh->prepare($sql);
        try {
            $sth->execute([$user_id]);
            return $sth->fetch();
        } catch (PDOException $e) {
            echo 'There was an SQL error ' . $e->getMessage() . ' SQL: ' . $sql;
        }
        return false;
    }

    public function getLoggedInUserData()
    {
        $user_id = $this->getLoggedInUserID();
        return $this->getUserData($user_id);
    }

    public function getLoggedInUserID()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        if (isset($_SESSION[$this->session_key])) {
            return $_SESSION[$this->session_key];
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
        if ($user_id = $this->whoIsLoggedIN()) {
            return $user_id;
        } else {
            header('Location: ' . $url);
            exit;
        }
    }
}
