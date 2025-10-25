<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class DB {
    private static ?mysqli $conn = null;

    public static function get(): mysqli {
        if (self::$conn === null) {
            $host = 'localhost';
            $user = 'root';        // <<< ajusta
            $pass = 'Miguel#1960';            // <<< ajusta
            $db   = 'orion';       // fijo por requerimiento

            $conn = new mysqli($host, $user, $pass, $db);
            $conn->set_charset('utf8mb4');
            self::$conn = $conn;
        }
        return self::$conn;
    }
}
?>