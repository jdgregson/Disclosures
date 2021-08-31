<?php

if (isset($_SERVER['HTTP_X_MS_APPLICATIONGUARD_INITIATED'])) {
    header('Location: https://'.str_repeat('a', 10000).'.example.com');
}

echo 'Thank you for not using WDAG! :D';

?>