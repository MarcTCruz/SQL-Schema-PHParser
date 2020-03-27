<?php
chdir(dirname(__FILE__));
include('../mysql_parser.php');

function println(...$args){
    foreach($args as $arg)
        echo $arg;
    
    echo "\n";
}
function printlln(...$args){
    echo "\n\n";
    foreach($args as $arg)
        echo $arg;
    
    echo "\n";
}
class timeIt{
    static $times   = [];
    static function time($key=null){
        self::$times[]  = [$key => microtime(true)]; 
    }
    static function showMeasures(){
        $times          = [];
        $lastValueKeys  = [];
        foreach(self::$times as $key_times)
            foreach($key_times as $key => $time){
                if(!isset($lastValueKeys[$key]))
                {
                    if(!isset($times[$key]))
                        $times[$key]    = 0;
                    $lastValueKeys[$key]    = $time;
                    continue;
                }
                $times[$key]    += $time - $lastValueKeys[$key];
                unset($lastValueKeys[$key]);
            }
        var_dump($times);
    }
}
$txt=<<<EOD
CREATE TABLE `test` (
    `pri` int(11) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `u` int(11) unsigned NOT NULL,
    `idx` int(11) DEFAULT 0,
    `idx2` varchar(11) NOT NULL DEFAULT '\\n',
    `field` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
    `field2` date NOT NULL DEFAULT current_timestamp(),
    `field3` datetime DEFAULT NULL ON UPDATE current_timestamp(),
    `field4` char(1) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `field5` int(11) GENERATED ALWAYS AS (`u` + 1) VIRTUAL COMMENT 'eee''f''f',
    `field6` int(11) GENERATED ALWAYS AS (`idx` + 4) STORED,
    `field 7`` 5` int(11) NOT NULL,
    `field8` enum('a','b','c',')','''') DEFAULT NULL,
    `field9` int(11) DEFAULT NULL,
    `field10` geometry DEFAULT NULL,
    `field,` int(11) NOT NULL,
    `fieldDec` decimal(10,9) NOT NULL,
    `fieldG` int(11) GENERATED ALWAYS AS (curdate() + 1) VIRTUAL,
    PRIMARY KEY (`pri`) USING BTREE,
    UNIQUE KEY `u` (`u`),
    UNIQUE KEY `field8` (`field8`,`field,`),
    KEY `idx` (`idx`),
    KEY `idx2` (`idx2`),
    FULLTEXT KEY `field4` (`field4`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8
EOD;

$injectionSchema    = <<<EOD
CREATE TABLE `users` '; 1 or 1;(
    `id` int(10) DEFAULT NULL,
    `username` varchar(20) DEFAULT NULL
)
EOD;
$data       = file_get_contents('./glitch_main.sql');
$schemas    = [];

$start = microtime(true);
    $parsedSchema   = mySQL_showCreateParser::parse($txt);
println(microtime(true) - $start);
file_put_contents('exported.dmp', var_export($parsedSchema, true));

$start = microtime(true);
    $parsedSchema   = mySQL_showCreateParser::parse($injectionSchema);
println(microtime(true) - $start);
file_put_contents('exported1.dmp', var_export($parsedSchema, true));

timeIt::time();
    $parsedSchemas   = mySQL_showCreateParser::pre_parse($data);
        foreach($parsedSchemas as $parsedSchema)
        {
            timeIt::time();
            $schemas[]  = $parsedSchema;
            //var_dump($parsedSchema);
            timeIt::time();
        }
timeIt::time();
file_put_contents('exported2.dmp', var_export($schemas, true));
timeIt::showMeasures();