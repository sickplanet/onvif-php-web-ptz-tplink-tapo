<?php
/**
 * ErrorHandler.php - Global error handling utility
 * Returns JSON for AJAX requests, Redirect for standard requests.
 */

class ErrorHandler {
    /**
     * Determine if request is an AJAX request
     */
    public static function isAjaxRequest(): bool {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        );
    }

    /**
     * Handle an error - JSON for AJAX, redirect for standard requests
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code (default 500)
     * @param string|null $redirectUrl URL to redirect to for non-AJAX (null = use referrer or BASE_URL)
     */
    public static function handleError(string $message, int $httpCode = 500, ?string $redirectUrl = null): void {
        http_response_code($httpCode);

        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $message]);
            exit;
        }

        // For standard requests, redirect with error message in session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash_error'] = $message;

        if ($redirectUrl === null) {
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL : '/');
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Handle success - JSON for AJAX, redirect for standard requests
     * 
     * @param array $data Success data for JSON response
     * @param string|null $redirectUrl URL to redirect to for non-AJAX
     * @param string|null $successMessage Flash message for redirect
     */
    public static function handleSuccess(array $data = [], ?string $redirectUrl = null, ?string $successMessage = null): void {
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode(array_merge(['ok' => true], $data));
            exit;
        }

        // For standard requests, redirect with success message
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($successMessage !== null) {
            $_SESSION['flash_success'] = $successMessage;
        }

        if ($redirectUrl === null) {
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL : '/');
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Wrap a callable with exception handling
     * 
     * @param callable $callback Function to execute
     * @param string|null $redirectUrl URL to redirect to on error for non-AJAX
     */
    public static function wrap(callable $callback, ?string $redirectUrl = null): void {
        try {
            $callback();
        } catch (Throwable $e) {
            error_log("ErrorHandler caught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            self::handleError($e->getMessage(), 500, $redirectUrl);
        }
    }

    /**
     * Send a JSON response with proper headers
     * 
     * @param array $data Data to encode as JSON
     * @param int $httpCode HTTP status code (default 200)
     */
    public static function json(array $data, int $httpCode = 200): void {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get and clear flash messages from session
     * 
     * @return array ['error' => string|null, 'success' => string|null]
     */
    public static function getFlashMessages(): array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        
        return ['error' => $error, 'success' => $success];
    }
}
