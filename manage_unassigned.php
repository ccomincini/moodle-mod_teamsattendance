if ($ajax) {
    // Clean any PHP warnings/errors from output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json');