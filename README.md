# SQL-Schema-PHParser
Translates proper SQL schemas from SHOW CREATE TABLE or dump to array

**Benchmark**
> **<=8 ms** parsing 114 tables from **tests/glitch_main.sql** on Inspiron I7 7000 10th Gen

**Usage**

    $schema =<<<EOD
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

    
    $parseds   = mySQL_showCreateParser::parse($schema);
    
   **$parsed will have the value:**
    
    	array (
	'fields' => 
	array (
		'pri' => 
		array (
		'type' => 'int',
		'length' => '11',
		'unsigned' => true,
		'zerofill' => true,
		'charset' => false,
		'collate' => false,
		'null' => false,
		'auto_increment' => true,
		'default' => false,
		'generated' => false,
		'comment' => false,
		),
		'u' => 
		array (
		'type' => 'int',
		'length' => '11',
		'unsigned' => true,
		'zerofill' => false,
		'charset' => false,
		'collate' => false,
		'null' => false,
		'auto_increment' => false,
		'default' => false,
		'generated' => false,
		'comment' => false,
		),
	... check its complete value on tests/exported.dmp
	... check its complete value on tests/exported.dmp
	... check its complete value on tests/exported.dmp
	),
	'keys' => 
	array (
		'PRIMARY' => 
		array (
		'type' => 
		array (
			'type' => 'PRIMARY',
			'keys' => 
			array (
			0 => 'pri',
			),
		),
		),
		'u' => 
		array (
		'type' => 
		array (
			'type' => 'UNIQUE',
			'keys' => 
			array (
			0 => 'u',
			),
		),
		),
		'field8' => 
		array (
		'type' => 
		array (
			'type' => 'UNIQUE',
			'keys' => 
			array (
			0 => 'field8',
			1 => 'field,',
			),
		),
		),
		'idx' => 
		array (
		'type' => 
		array (
			'type' => 'KEY',
			'keys' => 
			array (
			0 => 'idx',
			),
		),
		),
		'idx2' => 
		array (
		'type' => 
		array (
			'type' => 'KEY',
			'keys' => 
			array (
			0 => 'idx2',
			),
		),
		),
		'field4' => 
		array (
		'type' => 
		array (
			'type' => 'FULLTEXT',
			'keys' => 
			array (
			0 => 'field4',
			),
		),
		),
	),
	)
