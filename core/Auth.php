<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function register($name, $email, $password)
    {
        $existing = $this->db->selectOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $this->db->insert(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')",
            [$name, $email, $hashed]
        );

        return ['success' => true];
    }

    public function login($email, $password)
    {
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        if ((int) $user['is_active'] === 0) {
            return ['success' => false, 'message' => 'This account has been deactivated.'];
        }

        Session::start();
        session_regenerate_id(true);

        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);

        return ['success' => true, 'role' => $user['role']];
    }

    public static function isLoggedIn()
    {
        Session::start();
        return Session::has('user_id');
    }

    public static function isAdmin()
    {
        Session::start();
        return Session::get('user_role') === 'admin';
    }

    public static function requireLogin($redirectTo = 'login.php')
    {
        Session::start();
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireAdmin($redirectTo = 'login.php')
    {
        Session::start();
        if (!self::isLoggedIn() || Session::get('user_role') !== 'admin') {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function logout()
    {
        Session::start();
        Session::destroy();
    }
}
