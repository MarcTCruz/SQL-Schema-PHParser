<?php


class mySQL_showCreateParser
{
    static private $rows        = [];
    static private $currentRow  = '';
    static private $offset      = 0;
    const ASSOC                 = 0;
    const ARRAY                 = 1;
    const INCLUSIVE_ASSOC       = 2;

    static function pre_parse($sqlMulti, $flags=mySQL_showCreateParser::ASSOC)
    {
        /***If you're parsing schemas from a dump
            Use this function, 
            it'll remove comments and empty lines, 
            and pass it clean to be parsed
        */
        foreach(self::getNextSchema($sqlMulti) as $schema)
            yield self::parse($schema, $flags);

    }
    static function getNextSchema($sqlMulti){
        $sqlMulti   = strtr($sqlMulti, "\r", '');
        $rows       = explode("\n", $sqlMulti);
        $schema     = '';

        for($k = 0, $z = count($rows); $k < $z; ++$k)
        {
            $row = trim($rows[$k]);
            if(empty($row))
                continue;
            $rowType = substr($row, 0, 2);
            if(
                $rowType === '--' || //comment
                $rowType === '/*' || //comment
                $rowType === 'DR' //Drop table
            )
                continue;
            $schema .= $row . "\n";
            if($rowType === ') ')//Last line of Show Create
            {
                yield rtrim($schema);
                $schema = '';
            }
        }
    }
    static function parse($sqlCreate, $flags=mySQL_showCreateParser::ASSOC)
    {
        /***
        * This function can only parse a showCreate
        * generated directly from SHOW CREATE TABLE command
        */
        $fields     = [];
        $namedKeys  = [];
        $keys       = [];
        $columns    = [];
        $sqlCreate  = strtr($sqlCreate, "\r", '');
        self::$rows = explode("\n", $sqlCreate);
        for($k = 1, $z = count(self::$rows) -1; $k < $z; ++$k)
        {
            self::$offset                   = 0;//Reset line offset
            self::$currentRow               = trim(self::$rows[$k]);
 
            if(($columnName = self::getColumnName()) === false)//end of columns
                break;

            $fields[$columnName]['type']           = self::getType();
            $fields[$columnName]['length']         = self::getLength();
            $fields[$columnName]['unsigned']       = self::getUnsigned();
            $fields[$columnName]['zerofill']       = self::getZerofill();
            list($charset, $collate)               = self::getCharset();
            $fields[$columnName]['charset']        = $charset;
            $fields[$columnName]['collate']        = $collate;
            $fields[$columnName]['null']           = self::getNull();
            $fields[$columnName]['auto_increment'] = self::auto_increment();
            $fields[$columnName]['default']        = self::getDefault();
            $fields[$columnName]['generated']      = self::getGenerated();
            $fields[$columnName]['comment']        = self::getComment();
        }

        //Index come after definition types
        for(; $k < $z; ++$k)
        {
            self::$offset       = 0;
            $columns            = [];
            self::$currentRow   = trim(self::$rows[$k]);
            $type               = self::getWord([' ' => true]);
            $keyname            = 'PRIMARY';
            if($type !== 'PRIMARY')//primary doesnt have a keyname
                $keyname    = self::getWord(['`' => '`'], '`');
            do
            {
                $column     = self::getWord(['`' => '`'], '`');
                $columns[]  = $column;
            }while(self::$currentRow[self::$offset] !== ')');//Handles composite keys
            $namedKeys[$keyname] = ['type' => $type, 'keys' => $columns];
            
            if(!isset($keys[$type]))
                $keys[$type]    = [];
            
            $keys[$type]    = [$keyname => $columns];
        }

        self::$rows        = [];
        self::$currentRow  = '';
        return ['fields' => $fields, 'namedKeys' =>$namedKeys, 'keys' => $keys];
    }
    static private function getWord(array $delimiters, $startAfter = null)
    {
        /***
         * suffix escape is a delimiter escape which comes after delimmiter
         */
        $word   = '';
        if(!isset(self::$currentRow[self::$offset]))
            return false;
        
        
        if(isset($startAfter)){
            if((self::$offset = strpos(self::$currentRow, $startAfter, self::$offset)) === false)
                return false;
            ++self::$offset;
        }
        //'''
        while(isset(self::$currentRow[self::$offset]))
        {
            if(isset($delimiters[self::$currentRow[self::$offset]]) && 
            (!isset(self::$currentRow[++self::$offset]) ||
            $delimiters[self::$currentRow[self::$offset - 1]] !== self::$currentRow[self::$offset]))
                break;//we're at a delimiter and next is not an escape
            
            $word .= self::$currentRow[self::$offset++];
        }
        return $word;
    }
    static private function getColumnName()
    {
        if(self::$currentRow[self::$offset++] !== '`')
        {
            --self::$offset;
            return false;//Not a key, probably an index or just reached the end of keys...
        }
        $key = self::getWord(['`' => '`']);
        ++self::$offset;// 

        return $key;
    }
    static private function getType()
    {    
        $type   = self::getWord(['(' => true, ' '=> true, ',' => true]);
        --self::$offset;//goes back to delimiter

        return $type;
    }
    static private function getLength()
    {
        static $counter=0;
        $counter++;

        if(self::$currentRow[self::$offset] === ',' || self::$currentRow[self::$offset++] === ' ')//no length
            return '';

        if(self::$currentRow[self::$offset] !== "'")//it's a numeric length
        {
            $length = self::getWord([")"=> true]);
            ++self::$offset;
            return $length;
        }
        $values = [];
        do{
            $value = self::getWord(["'"=>"'"], "'");
            $values[]   = $value;
        }while(self::$currentRow[self::$offset] !== ')');
        self::$offset += 2;//) 

        return $values;
    }
    static private function getUnsigned()
    {
        if(self::$currentRow[self::$offset] !== 'u')
            return false;
        self::$offset += 9;//unsigned 
        return true;
    }
    static private function getZerofill()
    {
        if(self::$currentRow[self::$offset] !== 'z')
            return false;
        
        self::$offset += 9;//zerofill 
        return true;
    }
    static private function getCharset()
    {
        //Sample: CHARACTER SET utf8 COLLATE utf8_bin
        if(self::$currentRow[self::$offset] !== 'C') 
            return [false, false];
        self::$offset += 14;//CHARACTER SET 
        $charset = self::getWord([' ' => true]);
        if(self::$currentRow[self::$offset] !== 'C')//COLLATE
            return [$charset, false];
        self::$offset += 8;//COLLATE 
        $collate = self::getWord([' ' => true]);

        return [$charset, $collate];
    }
    static private function getNull()
    {
        if(self::$currentRow[self::$offset] !== 'N')
            return true;
        
        self::$offset += 8;//NOT NULL 
        if(isset(self::$currentRow[self::$offset + 1]))
            ++self::$offset;
        return false;
    }
    static private function auto_increment()
    {
        if(self::$currentRow[self::$offset] !== 'A')
            return false;
        
        self::$offset += 14;//AUTO_INCREMENT 
        if(isset(self::$currentRow[self::$offset + 1]))
            ++self::$offset;
        return true;
    }
    static private function getDefault()
    {
        if(self::$currentRow[self::$offset] !== 'D')
            return false;
        
        self::$offset += 8;//DEFAULT 
        if(self::$currentRow[self::$offset] === "'"){//a defined string
            ++self::$offset;
            return self::getWord(["'" => "'"]);
        }

        $default = self::getWord([' '=>true, ',' => true]);//a function, number or NULL
        if(!isset(self::$currentRow[self::$offset]))//goes back to comma
            --self::$offset;
    
        if($default === 'NULL')
            return null;
        
        return $default;
    }
    static private function getGenerated()
    {
        $value  = '';
        $word   = '';
        if(self::$currentRow[self::$offset] !== 'G')
            return false;

        self::$offset += 21;//GENERATED ALWAYS AS (
        while(true)
        {
            $word   = self::getWord([' ' => true]);
            if(self::$currentRow[self::$offset] === '+')
            {
                $value  .= $word . ' + ';
                self::$offset +=2;
                continue;
            }
            $value .= substr($word, 0, -1);//)
            break;
        }
        if(self::$currentRow[self::$offset] === 'V')
        {
            self::$offset += 7;//VIRTUAL
            if(isset(self::$currentRow[self::$offset+1]))
                ++self::$offset;
            return ['virtual' => $value];
        }

        self::$offset += 6;//STORED
        if(isset(self::$currentRow[self::$offset+1]))
            ++self::$offset;
        return ['stored' => $value];
    }
    static private function getComment()
    {
        if(self::$currentRow[self::$offset] === 'C')
        {
            self::$offset += 8;//COMMENT
            return self::getWord(["'" => "'"]);//After a comment there's just a comma at the end of line
        }
        return false;
    }
}
