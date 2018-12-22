<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function outputJSON($value)
{
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
    exit();
}
function exit200() {
    header("HTTP/1.1 200 OK");
    exit;
}
$method = null;

if (isset($_SERVER['REQUEST_METHOD'])) {
    $method = $_SERVER['REQUEST_METHOD'];
    switch ($method) {
        case 'HEAD':
        case 'OPTIONS':
        case 'GET':
        case 'POST':
        case 'PUT':
        case 'DELETE':
            break;
        default:
            $method = null;
            break;
    }
}

if ($method == 'OPTIONS' || $method == 'HEAD') {
    exit200();
}

if ($method == null) {
    header("HTTP/1.1 405 Method Not  Allowed");
    exit();
}
if (isset($_GET["XDEBUG_SESSION_START"]) || isset($_GET["XDEBUG_SESSION_STOP_NO_EXEC"])) {
    exit200();
}
if (isset($_GET["login"])) {
    exit200();
}
$headers = apache_request_headers();
$authorized = false;

if (isset($headers['X-Auth'])) {
    $data = $headers['X-Auth'];
    if (preg_match('/Basic\s+(.*)$/i', $data, $matches)) {
        list ($name, $password) = explode(':', base64_decode($matches[1]));
        $authorized = ($name == "user" && $password == "password");
    }
}

if (! $authorized) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

$prod = false;
if ($prod) {
    define('DB_HOST', 'ketmieuser.mysql.db');
    define('DB_LOGIN', 'ketmieuser');
    define('DB_PASSWORD', 'k3tM1aDm1n');
    define('DB_NAME', 'ketmieuser');
} else {
    define('DB_HOST', 'svg-db');
    define('DB_LOGIN', 'dbuser');
    define('DB_PASSWORD', 'dbuserpwd');
    define('DB_NAME', 'symbols_db');
}
$pdo = new PDO("mysql:host=" . DB_HOST . "; dbname=" . DB_NAME . ";", DB_LOGIN, DB_PASSWORD, array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"
));

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($method == "POST" || $method == "PUT") {
    $json = file_get_contents('php://input');
    $sym = json_decode($json);
    if (! property_exists($sym, "name"))
        $sym->name = "";
    if ($method == "POST") {
        $q = "INSERT INTO symbol (name, data, width, height, holes, pathLength) VALUES (?, ?, ?, ?, ?, ?);";
        $s = $pdo->prepare($q);
        $s->bindValue(1, $sym->name);
        $s->bindValue(2, $sym->data);
        $s->bindValue(3, $sym->width);
        $s->bindValue(4, $sym->height);
        $s->bindValue(5, $sym->holes);
        $s->bindValue(6, $sym->pathLength);
        try {
            $s->execute();
        } catch (PDOException $e) {
            // something wrong
            $m = $e->getMessage();
        }
        $sym = new stdClass();
        $sym->id = $pdo->lastInsertId('symbol');
        outputJSON($sym);
    } else {
        $keys = array();
        $m = array();
        $i = 1;
        foreach ($sym as $key => $value) {
            if ($key == "id")
                continue;
            $keys[] = $key . "=?";
            $m[$i ++] = $value;
        }
        $m[$i ++] = $sym->id;
        $q = "UPDATE symbol SET " . implode(", ", $keys) . " WHERE id=?;";
        $s = $pdo->prepare($q);
        
        foreach ($m as $i => $value) {
            $s->bindValue(intval($i), $value);
        }
        $s->execute();
        exit();
    }
}

if ($method == "DELETE") {
    if (isset($_GET["id"])) {
        $pdo->query("DELETE FROM symbol WHERE id=" . $_GET["id"]);
    }
    exit();
}

if ($method == "GET") {
    $q = "SELECT * FROM symbol";
    $isArray = true;
    if (isset($_GET["id"])) {
        $q .= " WHERE id=" . $_GET["id"];
        $isArray = false;
    }
    $s = $pdo->query($q, PDO::FETCH_OBJ);
    $rows = $isArray ? $s->fetchAll() : $s->fetch();
    outputJSON($rows);
}
