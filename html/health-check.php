<?php

if (\file_exists('stop.txt')) {
    \header('HTTP/1.1 503 Service Unavailable');
} else {
    if (\file_exists('/var/www/bnls_2026/IMG-0095-69bf9fd7a2ea6.jpg')) {
        \header('HTTP/1.1 200 OK');
    } else {
        \header('HTTP/1.1 503 Service Unavailable');
    }
}
echo '<meta http-equiv="refresh" content="5">';
