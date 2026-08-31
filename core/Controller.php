<?php

/**
 * Base Controller
 * Menyediakan helper render view dengan layout, dan redirect.
 */
abstract class Controller
{
    /**
     * Render sebuah view di dalam layout utama (main.php)
     */
    protected function view(string $viewPath, array $data = [])
    {
        extract($data);
        $viewFile = ROOT_PATH . '/app/views/' . $viewPath . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View tidak ditemukan: {$viewPath}");
        }

        // main.php akan meng-include $viewFile di dalam layout (header/sidebar/footer)
        require ROOT_PATH . '/app/views/layouts/main.php';
    }

    /**
     * Render view TANPA layout (dipakai untuk halaman login)
     */
    protected function viewPlain(string $viewPath, array $data = [])
    {
        extract($data);
        $viewFile = ROOT_PATH . '/app/views/' . $viewPath . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View tidak ditemukan: {$viewPath}");
        }

        require $viewFile;
    }

    protected function redirect(string $module, string $action = 'index', array $query = [])
    {
        header('Location: ' . route($module, $action, $query));
        exit;
    }

    protected function json(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
