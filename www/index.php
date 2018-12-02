<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function outputJSON($value)
{
    echo json_encode($value, JSON_PRETTY_PRINT);
    exit();
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

if ($method == null) {
    header("HTTP/1.1 405 Method Not  Allowed");
    exit();
}

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: HEAD, GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, X-Api-Session-Id");
if ($method == 'OPTIONS' || $method == 'HEAD') {
    header("HTTP/1.1 200 OK");
    exit();
}

$pdo = new PDO("mysql:host=svg-db; dbname=symbols_db;", "dbuser", "dbuserpwd", array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"
));

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($method == "POST" || $method == "PUT") {
    $sym = json_decode(file_get_contents('php://input'));
    if ($method == "POST") {
        $q = "INSERT INTO symbol (name, data, width, height) VALUES (?, ?, ?, ?);";
        $s = $pdo->prepare($q);
        $s->bindValue(1, $sym->name, PDO::PARAM_STR);
        $s->bindValue(2, $sym->data, PDO::PARAM_STR);
        $s->bindValue(3, $sym->width, PDO::PARAM_INT);
        $s->bindValue(4, $sym->height, PDO::PARAM_INT);
        $s->execute();
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