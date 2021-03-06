<?php

//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

    /**
     *  Authorization script by kapehh [MODIFICATION for WordPress]
     *  
     *  URLS:
     *  
     *   For authorization
     *     /kph/authorization.php?action=login
     *     /kph/authorization.php?action=register
     *  
     *   For client-side, client send JOIN request for player (UUID of player, AccessToken from authorization, ServerID from server)
     *     /kph/authorization.php?action=join
     *  
     *   For server-side, server check Username and ServerID
     *     /kph/authorization.php?action=hasjoined
     *  
     *  
     *  MySQL Table:
     *  
     *   CREATE TABLE `kph_players` (
     *       `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
     *       `username` VARCHAR(255) NOT NULL DEFAULT '',
     *       `password` VARCHAR(255) NOT NULL DEFAULT '',
     *       `uuid` VARCHAR(255) NOT NULL DEFAULT '',
     *       `accessToken` VARCHAR(255) NOT NULL DEFAULT '',
     *       `serverID` VARCHAR(255) NOT NULL DEFAULT '',
     *       `skin` VARCHAR(255) NOT NULL DEFAULT '',
     *       `cloak` VARCHAR(255) NOT NULL DEFAULT '',
     *       PRIMARY KEY (`id`)
     *   )
     *
     *
     *  Minecraft Client arguments:
     *
     *    javaw
     *      -Djava.library.path="%APPDATA%\.minecraft\versions\XXX\natives"
     *      -cp "all libraries;%APPDATA%\.minecraft\versions\XXX\XXX.jar"
     *      net.minecraft.client.main.Main
     *      --username {$USERNAME}
     *      --version 1.10
     *      --gameDir %APPDATA%\.minecraft
     *      --assetsDir %APPDATA%\.minecraft\assets
     *      --assetIndex 1.10
     *      --uuid {$UUID}
     *      --accessToken {$ACCESSTOKEN}
     *      --userType mojang
     *      --tweakClass optifine.OptiFineTweaker
     */

    $DB_HOST = "localhost";
    $DB_PORT = 3306;
    $DB_USER = "karen";
    $DB_PASS = "password";
    $DB_NAME = "mc_test";
    $DB_TABLE = "kph_players";
    
    require_once('wordpress_passwordhashing.php');
    $wp_hasher = new PasswordHash(8, true);

    /**
     * MYSQL
     */
    
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    
    if ($mysqli->connect_errno) {
        dir("Не удалось подключиться к MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    }
    

    /**
     * MAIN LOGIC
     */
    
    if (!isset($_GET['action'])) {
        die();
    }
    
    $action = $_GET['action'];
    
    switch ($action) {
        
        // Launcher-side: Register
        
        case "register":
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = $_POST['username'];
                $password = get_pass_hash($_POST['password']);
                $uuid = uuid_from_nickname($username);
                
                if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
                    die('{"success": false, "errorMessage": "Некорректный никнейм! Разрешены символы: a-z, A-Z, 0-9 и _"}');
                }
                
                // Check user exists
                $stmt = $mysqli->prepare("SELECT `id` FROM {$DB_TABLE} WHERE `username` = ?");
                $stmt->bind_param("s", $username);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $item = $res->fetch_array(MYSQLI_ASSOC);
                    
                    if ($item) {
                        die('{"success": false, "errorMessage": "Такой пользователь уже существует!"}');
                    }
                }
                
                $stmt = $mysqli->prepare("INSERT INTO {$DB_TABLE}(`username`, `password`, `uuid`) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $password, $uuid);
                
                if ($stmt->execute()) {
                    die('{"success": true}');
                } else {
                    die('{"success": false, "errorMessage": "Ошибка при запросе к БД!"}');
                }
            } else {
                die('{"success": false, "errorMessage": "Некорректный запрос!"}');
            }
            break;
        
        // Launcher-side: Login
        
        case "login":
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = $_POST['username'];
                $password = $_POST['password'];
                
                if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
                    die('{"success": false, "errorMessage": "Некорректный никнейм! Разрешены символы: a-z, A-Z, 0-9 и _"}');
                }
                
                $stmt = $mysqli->prepare("SELECT `id`, `username`, `password`, `uuid` FROM {$DB_TABLE} WHERE `username` = ?");
                $stmt->bind_param("s", $username);
                
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $item = $res->fetch_array(MYSQLI_ASSOC);
                    
                    if ($item && check_pass_hash($password, $item["password"])) {
                        $access_token = generate_access_token();
                        $item["accessToken"] = $access_token;
                        $item["success"] = true;
                        
                        // Update access token
                        $mysqli->query("UPDATE {$DB_TABLE} SET `accessToken` = '{$access_token}' WHERE `id` = {$item["id"]}");
                        
                        unset($item["id"]); // remove id from response
                        die(json_encode($item));
                    } else {
                        die('{"success": false, "errorMessage": "Ошибка в имени пользователя или пароле!"}');
                    }
                } else {
                    die('{"success": false, "errorMessage": "Ошибка при запросе к БД!"}');
                }
            } else {
                die('{"success": false, "errorMessage": "Некорректный запрос!"}');
            }
            break;
        
        
        // Client-side: join
        
        case "join":
            // Проверяем, что мы получили POST-запрос с JSON-содержимым
            if (($_SERVER['REQUEST_METHOD'] == 'POST') && (stripos($_SERVER["CONTENT_TYPE"], "application/json") === 0)) {
                $strData = file_get_contents('php://input');
                $data = json_decode($strData, TRUE);
                $access_token = $data["accessToken"];
                $selected_profile = $data["selectedProfile"];
                $server_id = $data["serverId"];
                
                //file_put_contents('log.txt', $strData.PHP_EOL , FILE_APPEND);
                
                $stmt = $mysqli->prepare("SELECT `id` FROM {$DB_TABLE} WHERE `uuid` = ? AND `accessToken` = ?");
                $stmt->bind_param("ss", $selected_profile, $access_token);
                
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $item = $res->fetch_array(MYSQLI_ASSOC);
                    
                    if ($item) {
                        // Update access token
                        $stmt = $mysqli->prepare("UPDATE {$DB_TABLE} SET `serverID` = ? WHERE `id` = ?");
                        $stmt->bind_param("si", $server_id, $item["id"]);
                        $stmt->execute();
                        die();
                    } else {
                        die('{"error": "Auth error", "errorMessage": "Authorization error! Please relogin!", "cause": "Auth error"}');
                    }
                } else {
                    die('{"error": "Auth error", "errorMessage": "Error query DB!", "cause": "Auth error"}');
                }
            } else {
                die('{"error": "Auth error", "errorMessage": "Invalid request! Use POST request and content-type application/json.", "cause": "Auth error"}');
            }
            break;
            
        // Server-side: hasJoined
        
        case "hasjoined":
            if (isset($_GET['username']) && isset($_GET['serverId'])) {
                $username = $_GET['username'];
                $server_id = $_GET["serverId"];
                
                $stmt = $mysqli->prepare("SELECT `username`, `uuid`, `skin`, `cloak` FROM {$DB_TABLE} WHERE `username` = ? AND `serverID` = ?");
                $stmt->bind_param("ss", $username, $server_id);
                
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $item = $res->fetch_array(MYSQLI_ASSOC);
                    
                    if ($item) {
                        $textures = array("SKIN" => array("url" => $item["skin"]));
                        
                        // if cloak exists then add to textures
                        if (strlen(trim($item["cloak"])) > 0) {
                            $textures["CAPE"] = array("url" => $item["cloak"]);
                        }
                        
                        $properties = array(
                            "timestamp" => time(),
                            "profileId" => $item["uuid"],
                            "profileName" => $item["username"],
                            "textures" => $textures
                        );
                        
                        $user_data = array(
                            "id" => $item["uuid"],
                            "name" => $item["username"],
                            "properties" => array(
                                array(
                                    "name" => "textures",
                                    "value" => base64_encode(json_encode($properties))
                                )
                            )
                        );
                        
                        //file_put_contents('log.txt', json_encode($user_data).PHP_EOL , FILE_APPEND);
                        die(json_encode($user_data));
                    } else {
                        die();
                    }
                }
                die();
                
            }
            break;
            
    }
    
    
    /**
     * CORE FUNCTIONS
     */
    
    function get_pass_hash($pass) {
        global $wp_hasher;
        return $wp_hasher->HashPassword(trim($pass));
    }
    
    function check_pass_hash($pass, $hash) {
        global $wp_hasher;
        return $wp_hasher->CheckPassword($pass, $hash);
    }
    
    function generate_access_token() {
        $first_part = rand(1000000000, 2147483647) . rand(1000000000, 2147483647);
        $second_part = rand(1000000000, 2147483647) . rand(1000000000, 2147483647);
        $full_part = md5($first_part) . md5($second_part);
        return str_shuffle($full_part);
    }
    
    function uuid_from_nickname($string) {
        $string = md5("OfflinePlayer:" . $string);
        return $string;
    }
    