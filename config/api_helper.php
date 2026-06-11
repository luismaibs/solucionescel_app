<?php

/**
 * Helper para respuestas API estandarizadas.
 * Siempre usa "ok": true|false. Códigos HTTP consistentes.
 *
 * @param array $data Datos a devolver (se añade "ok" automáticamente)
 * @param int   $code Código HTTP: 200 éxito, 400 validación, 401 auth, 404 no encontrado, 500 error
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $data['ok'] = ($code >= 200 && $code < 300);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
