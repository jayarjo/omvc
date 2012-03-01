<?php

require_once(dirname(__FILE__) . '/Conf.php');

require_once(Conf::$conf['dir']['libs'] . "/App.php");
require_once(Conf::$conf['dir']['libs'] . "/SessionData.php");
require_once(Conf::$conf['dir']['libs'] . "/DB.php");
require_once(Conf::$conf['dir']['libs'] . "/Auth.php");
require_once(Conf::$conf['dir']['libs'] . "/Cypher.php");
require_once(Conf::$conf['dir']['libs'] . "/CurlCache.php");
require_once(Conf::$conf['dir']['libs'] . "/Cache.php");
require_once(Conf::$conf['dir']['libs'] . "/Error.php");
require_once(Conf::$conf['dir']['libs'] . "/JSON.php");
require_once(Conf::$conf['dir']['libs'] . "/Controller.php");
require_once(Conf::$conf['dir']['libs'] . "/Model.php");
require_once(Conf::$conf['dir']['libs'] . "/View.php");

new App(Conf::$conf);




?>