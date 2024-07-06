<?php

if (\file_exists('stop.txt')) {
    header('HTTP/1.1 503 Service Unavailable');
} else {
    header('HTTP/1.1 200 OK');
}
echo '<meta http-equiv="refresh" content="5">';
