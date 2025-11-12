<?php
// logout.php

// 1. Iniciar la sesión.
session_start();

// 2. Destruir todas las variables de sesión.
$_SESSION = array();

// 3. Destruir la sesión por completo.
session_destroy();

// 4. Redirigir al usuario a la página de inicio de sesión.
// ¡RUTA FINALMENTE CORRECTA!
header("Location: index.html");
exit();

?>