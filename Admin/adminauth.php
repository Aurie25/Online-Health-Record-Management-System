<?php

class Auth {
    private $conn;
    private $maxAttempts = 5;
    private $lockMinutes = 15;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {

        $query = "SELECT * FROM admins 
                  WHERE username = :username 
                  OR email = :username 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

             $admin['failed_attempts'] = $admin['failed_attempts'] ?? 0;
            $admin['lock_until'] = $admin['lock_until'] ?? null;
            
            // Check if account is locked
            if ($admin['lock_until'] && strtotime($admin['lock_until']) > time()) {
                return "Account locked. Try again later.";
            }

            if (password_verify($password, $admin['password'])) {

                // Reset failed attempts
                $this->resetAttempts($admin['id']);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];

                $this->updateLastLogin($admin['id']);
                $this->logActivity($admin['id'], "login", "Admin logged in");

                return true;

            } else {
                $this->registerFailedAttempt($admin);
                return "Invalid credentials.";
            }
        }

        return "Invalid credentials.";
    }

    private function registerFailedAttempt($admin) {

        $failed = $admin['failed_attempts'] + 1;

        if ($failed >= $this->maxAttempts) {

            $lockUntil = date("Y-m-d H:i:s", strtotime("+{$this->lockMinutes} minutes"));

            $query = "UPDATE admins 
                      SET failed_attempts = :failed,
                          lock_until = :lock_until
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':failed' => $failed,
                ':lock_until' => $lockUntil,
                ':id' => $admin['id']
            ]);

        } else {

            $query = "UPDATE admins 
                      SET failed_attempts = :failed
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':failed' => $failed,
                ':id' => $admin['id']
            ]);
        }
    }

    private function resetAttempts($admin_id) {
        $query = "UPDATE admins 
                  SET failed_attempts = 0,
                      lock_until = NULL
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $admin_id]);
    }

    private function updateLastLogin($admin_id) {
        $query = "UPDATE admins SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $admin_id]);
    }

    private function logActivity($admin_id, $action, $details) {
        try {
            $query = "INSERT INTO activity_logs 
                      (admin_id, action, details, ip_address, timestamp)
                      VALUES (:admin_id, :action, :details, :ip, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':action' => $action,
                ':details' => $details,
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {}
    }

    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }

    public function isLoggedIn() {
        return isset($_SESSION['admin_id']);
    }

    public function redirectIfNotLoggedIn() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }

    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['admin_id'], "logout", "Admin logged out");
        }

        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
?>