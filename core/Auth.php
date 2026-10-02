<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

/**
 * Authentication with per-area "guards".
 *
 * The storefront and the admin panel each keep their own independent login
 * state, so an admin and a customer can be signed in at the same time in the
 * same browser without one logging the other out - and each area has its own
 * logout. The active guard is worked out from the request: anything under
 * /admin/ uses the admin guard, everything else (the storefront under /public/)
 * uses the customer guard.
 */
class Auth
{
    const CUSTOMER = 'customer';
    const ADMIN = 'admin';

    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    // The current area's guard: 'admin' for the panel, 'customer' otherwise.
    public static function guard()
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        return strpos($script, '/admin/') !== false ? self::ADMIN : self::CUSTOMER;
    }

    // Each guard gets its own slot in the session so the two never clash.
    private static function sessionKey($guard)
    {
        return 'auth_' . $guard;
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

    /**
     * Checks an email/password WITHOUT touching the session.
     *
     * Returns ['user' => $row] when the credentials are good, or
     * ['error' => 'message'] when they are not. Splitting this out lets a
     * caller decide WHICH area the account is allowed into before any session
     * is created.
     */
    public function authenticate($email, $password)
    {
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['error' => 'Incorrect email or password.'];
        }

        if ((int) $user['is_active'] === 0) {
            return ['error' => 'This account has been deactivated.'];
        }

        return ['user' => $user];
    }

    /**
     * Starts a session for one guard from an already-verified user row.
     * Other guards are left completely alone.
     */
    public function establish($user, $guard = null)
    {
        $guard = $guard ?: self::guard();

        Session::start();
        // Regenerate the id (keeps session data) to guard against fixation.
        session_regenerate_id(true);

        Session::set(self::sessionKey($guard), [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
    }

    public function login($email, $password, $guard = null)
    {
        $result = $this->authenticate($email, $password);

        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']];
        }

        $user = $result['user'];
        $this->establish($user, $guard);

        return ['success' => true, 'role' => $user['role']];
    }

    // ---- Guard-aware accessors -------------------------------------------

    public static function user($guard = null)
    {
        Session::start();
        $user = Session::get(self::sessionKey($guard ?: self::guard()), null);

        return is_array($user) ? $user : null;
    }

    public static function id($guard = null)
    {
        $user = self::user($guard);
        return $user['id'] ?? null;
    }

    public static function name($guard = null)
    {
        $user = self::user($guard);
        return $user['name'] ?? null;
    }

    public static function role($guard = null)
    {
        $user = self::user($guard);
        return $user['role'] ?? null;
    }

    // Keep the cached name in sync after a profile update.
    public static function updateName($name, $guard = null)
    {
        Session::start();
        $key = self::sessionKey($guard ?: self::guard());
        $user = Session::get($key, null);

        if (is_array($user)) {
            $user['name'] = $name;
            Session::set($key, $user);
        }
    }

    public static function isLoggedIn($guard = null)
    {
        return self::user($guard) !== null;
    }

    public static function isAdmin($guard = null)
    {
        return self::role($guard) === self::ADMIN;
    }

    public static function requireLogin($redirectTo = 'login.php', $guard = null)
    {
        Session::start();
        if (!self::isLoggedIn($guard)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireAdmin($redirectTo = 'login.php', $guard = null)
    {
        Session::start();
        if (!self::isLoggedIn($guard) || self::role($guard) !== self::ADMIN) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    // Log out only this area's guard. The other guard (a customer signed in on
    // the storefront, or an admin in the panel) stays logged in.
    public static function logout($guard = null)
    {
        Session::start();
        Session::remove(self::sessionKey($guard ?: self::guard()));
        session_regenerate_id(true);
    }
}
