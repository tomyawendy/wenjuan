<?php
header('Content-Type: application/json; charset=utf-8');

http_response_code(404);
echo json_encode(['ok' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE);
