# Minecraft-Yggdrasil-Authorization
Авторизация Yggdrasil для Minecraft

```
Authorization script by kapehh

URLS:

 For authorization
   /kph/authorization.php?action=login
   /kph/authorization.php?action=register

 For client-side, client send JOIN request for player (UUID of player, AccessToken from authorization, ServerID from server)
   /kph/authorization.php?action=join

 For server-side, server check Username and ServerID
   /kph/authorization.php?action=hasjoined


MySQL Table:

 CREATE TABLE `kph_players` (
     `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
     `username` VARCHAR(255) NOT NULL DEFAULT '',
     `password` VARCHAR(255) NOT NULL DEFAULT '',
     `uuid` VARCHAR(255) NOT NULL DEFAULT '',
     `accessToken` VARCHAR(255) NOT NULL DEFAULT '',
     `serverID` VARCHAR(255) NOT NULL DEFAULT '',
     `skin` VARCHAR(255) NOT NULL DEFAULT '',
     `cloak` VARCHAR(255) NOT NULL DEFAULT '',
     PRIMARY KEY (`id`)
 )


Minecraft Client arguments:

  javaw
    -Djava.library.path="%APPDATA%\.minecraft\versions\XXX\natives"
    -cp "all libraries;%APPDATA%\.minecraft\versions\XXX\XXX.jar"
    net.minecraft.client.main.Main
    --username {$USERNAME}
    --version 1.10
    --gameDir %APPDATA%\.minecraft
    --assetsDir %APPDATA%\.minecraft\assets
    --assetIndex 1.10
    --uuid {$UUID}
    --accessToken {$ACCESSTOKEN}
    --userType mojang
    --tweakClass optifine.OptiFineTweaker
```

## Алгоритм авторизации
* Лаунчер спрашивает у пользователя логин и пароль, а затем отправляет их на Сайт;
* Сайт проверяет правильность введенных данных и отправляет обратно Лаунчеру : Ник игрока, UUID, accessToken ;
* Лаунчер запускает Клиент игры с параметрами, полученными с предыдущего пункта;
* Игрок в Клиенте выбирает Сервер и нажимает Подключиться;
* Клиент знакомится с Сервером. Сервер отдает Клиенту ServerID - уникальный номер сервера для подключения. Клиент отдает Серверу свой ник (username);
* Клиент запрашивает разрешение у Сайта авторизации, отдавая ему свой accessToken, UUID и ServerID;
* Сайт проверяет правильность данных и если все ОК, то запоминает ServerID;
* Клиент получил разрешение от Сайта и посылает на Сервер запрос на подключение;
* Сервер, чтобы впустить Клиента спрашивает у Сайта авторизации его данные, отдавая ему Ник игрока и свой ServerID;
* Сайт передаёт Серверу информацию о параметрах игрока, чем разрешает тому войти;
* Клиент успешно заходит на сервер.

## Модификация YggdrasilMinecraftSessionService
В файле YggdrasilMinecraftSessionService.java содержится измененный код для версии 1.5.22 (\com\mojang\authlib\1.5.22\authlib-1.5.22.jar) результирующие *.class файлы можно поместить и в клиент и в сервер.

## PHP
Для отловка ошибок на сервере, может потребоваться использование:
```php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
```
И для mysqli потребуется установить:
```
apt-get install php5-mysqlnd
```
