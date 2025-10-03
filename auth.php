<?php
// includes/auth.php
session_start();

class Auth {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureSessionsTable();
    }
    
    /**
     * Create sessions table if it doesn't exist
     */
    private function ensureSessionsTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    session_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    session_token VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_session_token (session_token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB
            ");
        } catch (PDOException $e) {
            error_log("Session table creation error: " . $e->getMessage());
        }
    }
    
    /**
     * Register a new user using your existing table structure
     */
    public function register($userData) {
        try {
            // Validate input
            $validation = $this->validateRegistrationData($userData);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Check if email already exists
            if ($this->emailExists($userData['email'])) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert user into your existing users table
            $stmt = $this->pdo->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password, role) 
                VALUES (?, ?, ?, ?, ?, 'customer')
            ");
            
            $stmt->execute([
                trim($userData['firstName']),
                trim($userData['lastName']),
                strtolower(trim($userData['email'])),
                trim($userData['phone']),
                $passwordHash
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            return [
                'success' => true, 
                'message' => 'Account created successfully! You can now sign in.',
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Login user using your existing table structure
     */
    public function login($email, $password, $rememberMe = false, $ipAddress = null, $userAgent = null) {
        try {
            $email = strtolower(trim($email));
            
            // Get user data from your existing users table
            $stmt = $this->pdo->prepare("
                SELECT user_id, first_name, last_name, email, password, role
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Success - create session
            $sessionToken = $this->createSession($user['user_id'], $rememberMe, $ipAddress, $userAgent);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['user_id'],
                    'firstName' => $user['first_name'],
                    'lastName' => $user['last_name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'session_token' => $sessionToken
            ];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Validate user session using your existing table structure
     */
    public function validateSession($sessionToken) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.user_id, s.expires_at, u.first_name, u.last_name, u.email, u.role
                FROM user_sessions s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.session_token = ? AND s.expires_at > NOW()
            ");
            $stmt->execute([$sessionToken]);
            $session = $stmt->fetch();
            
            if (!$session) {
                return ['valid' => false];
            }
            
            // Extend session if close to expiry (within 30 minutes)
            $expiresAt = new DateTime($session['expires_at']);
            $now = new DateTime();
            $diff = $expiresAt->getTimestamp() - $now->getTimestamp();
            
            if ($diff < 1800) { // Less than 30 minutes
                $this->extendSession($sessionToken);
            }
            
            return [
                'valid' => true,
                'user' => [
                    'id' => $session['user_id'],
                    'firstName' => $session['first_name'],
                    'lastName' => $session['last_name'],
                    'email' => $session['email'],
                    'role' => $session['role']
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Session validation error: " . $e->getMessage());
            return ['valid' => false];
        }
    }
    
    /**
     * Logout user
     */
    public function logout($sessionToken) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$sessionToken]);
            return ['success' => true, 'message' => 'Logged out successfully'];
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }
    
     /**
     * Request password reset using your existing table structure
     */
    public function requestPasswordReset($email) {
        try {
            $email = strtolower(trim($email));
            
            // Check if user exists in your existing users table
            $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                // Return success even if email doesn't exist (security)
                return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
            }
            
            // For now, just return success message
            // TODO: Implement password reset functionality
            // You can extend your users table to add reset_token and reset_expires columns
            
            return ['success' => true, 'message' => 'Password reset functionality will be implemented. Please contact support.'];
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Reset request failed. Please try again.'];
        }
    }
    
    // Private helper methods
    
    private function validateRegistrationData($data) {
        if (empty($data['firstName']) || strlen($data['firstName']) < 2) {
            return ['valid' => false, 'message' => 'First name is required'];
        }
        
        if (empty($data['lastName']) || strlen($data['lastName']) < 2) {
            return ['valid' => false, 'message' => 'Last name is required'];
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Valid email is required'];
        }
        
        if (empty($data['password']) || strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            return ['valid' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }
        
        if ($data['password'] !== $data['confirmPassword']) {
            return ['valid' => false, 'message' => 'Passwords do not match'];
        }
        
        return ['valid' => true];
    }
    
     private function emailExists($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function createSession($userId, $rememberMe, $ipAddress, $userAgent) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = $rememberMe 
            ? date('Y-m-d H:i:s', strtotime('+30 days'))
            : date('Y-m-d H:i:s', strtotime('+' . SESSION_TIMEOUT . ' seconds'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionToken, $ipAddress, $userAgent, $expiresAt]);
        
        return $sessionToken;
    }
    
    private function extendSession($sessionToken) {
        $newExpiry = date('Y-m-d H:i:s', strtotime('+' . SESSION_TIMEOUT . ' seconds'));
        $stmt = $this->pdo->prepare("UPDATE user_sessions SET expires_at = ? WHERE session_token = ?");
        $stmt->execute([$newExpiry, $sessionToken]);
    }
    
     // Simplified for your existing database - you can extend users table later if needed
    private function logLoginAttempt($email, $ipAddress, $success, $userAgent) {
        // Optional: Create a login_attempts table later if you want detailed logging
        // For now, just log to audit_log table if it exists
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (user_id, action, details) 
                VALUES (
                    (SELECT user_id FROM users WHERE email = ?), 
                    'login_attempt', 
                    CONCAT('Success: ', ?, ', IP: ', ?, ', User-Agent: ', SUBSTRING(?, 1, 255))
                )
            ");
            $stmt->execute([$email, $success ? 'true' : 'false', $ipAddress, $userAgent]);
        } catch (PDOException $e) {
            // Ignore if audit_log doesn't exist or fails
        }
    }
}



// --- Compatibility wrappers and global auth instance ---
// Instantiate Database and Auth so procedural wrappers can use them.
require_once __DIR__ . '/../config/db.php';
try {
    $__ozyde_db_instance = new Database();
    $__ozyde_pdo = $__ozyde_db_instance->getConnection();
    $__ozyde_auth = new Auth($__ozyde_pdo);
} catch (Exception $e) {
    // If DB is not configured yet, leave wrappers to fail gracefully.
    $__ozyde_pdo = null;
    $__ozyde_auth = null;
}

/**
 * Procedural logout() wrapper expected by some controllers.
 * It will remove the session token from the DB (if present) and destroy PHP session.
 */
function logout() {
    global $__ozyde_pdo, $__ozyde_auth;
    if (session_status() === PHP_SESSION_NONE) session_start();

    $sessionToken = $_SESSION['session_token'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if ($__ozyde_pdo) {
        try {
            if ($sessionToken) {
                $stmt = $__ozyde_pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
                $stmt->execute([$sessionToken]);
            } elseif ($userId) {
                $stmt = $__ozyde_pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
        } catch (PDOException $e) {
            // ignore DB errors during logout
            error_log('Logout DB cleanup error: ' . $e->getMessage());
        }
    }

    // Destroy PHP session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

?>