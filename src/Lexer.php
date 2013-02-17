<?php
namespace phpcc;

class Lexer {
    protected $names = [];
    protected $patterns = [];
    protected $groupOffsets = [];
    protected $keywords = [];
    protected $defines = [];

    function __construct($m = null, $case_sensitive = true) {
        if (empty($m)) {
            return;
        }
        $this->init($m, $case_sensitive);
    }

    function hasPattern($name) {
        return isset($this->defines[$name]);
    }
    
    function init($m, $case_sensitive = true) {
        $patterns = [];
        $this->patterns = [];
        $this->groupOffsets = [];
        $offset = 0;
        $i = 0;
        $flag = $case_sensitive ? 'SAm' : 'SAmi';

        foreach ($m as $name=>$regex) {
            if (is_int($name)) {
                $this->names[$i]= $regex;
                $regex = preg_quote($regex);
                $this->keywords[$regex] = true;
            } else {
                $this->names[$i]= $name;
            }
            $this->defines[$this->names[$i]] = "($regex)$flag";

            $patterns[$i]= "($regex)";
            
            for ($j = 0; $j < $this->countGroups($regex) + 1; $j++) {
                $offset++; 
                $this->groupOffsets[$offset]= $i;
            }
            $i++;
        }
        $count = count($patterns);

        for ($i = 0; $i < $count; $i++) {
            $this->patterns[$i] = '('.implode('|', array_slice($patterns, $i)).")$flag";
        }
    }
    
    function dump() {
        return [$this->names, $this->patterns, $this->groupOffsets];
    }
    
    function load($a) {
        list($this->names, $this->patterns, $this->groupOffsets) = $a;
    }
    
    function countGroups($pattern) {
        $gp = '/(?<!\\\\)(?:\\\\\\\\)*\((?!\?)/S';
        $ret = preg_match_all($gp, $pattern);
        return $ret;
    }

    function expectToken($s, $offset, $name) {
        if (!isset($this->defines[$name])) {
            throw new \Exception("no such token $name");
        }
        $pattern = $this->defines[$name];
        if (preg_match($pattern, $s, $m, null, $offset)) {
            return [$name, $m[0]];
        }
        return null;
    }

    function expectString($s, $offset, $str) {
        $len = strlen($str);
        if (substr($s, $offset, $len) === $str) {
            return true;
        }
        return false;
    }

    function match($s, $offset, $prefer = null) {
        $name = $value = '';
        $cur = 0;
        $pattern = $this->patterns[0];
        while (preg_match($pattern, $s, $m, null, $offset)) {
            $v = $m[0];
            end($m);
            $group = key($m) + $cur;
            $pattern_id = $this->groupOffsets[$group];
            $n = $this->names[$pattern_id];
            $replace = false;
            if (strlen($v) > strlen($value)) {
                $replace = true;
            } elseif ($v === $value) {
                if ($prefer === null) {
                    if (isset($this->keywords[$n])) {
                        $replace = true;
                    }
                } elseif (is_string($prefer)) {
                    if ($n === $prefer) {
                        $replace = true;
                    }
                } elseif (is_array($prefer) && !in_array($name, $prefer)) {
                    if (in_array($n, $prefer)) {
                        $replace = true;
                    } elseif (isset($this->keywords[$n])) {
                        $replace = true;
                    }
                }
            }
            if ($replace) {
                $value = $v;
                $name = $n;
            }

            $cur = $pattern_id + 1;
            if (!isset($this->patterns[$cur])) {
                break;
            }
            $pattern = $this->patterns[$cur];
        }
        if ($cur == 0) {
            return null;
        }
        return [$name, $value];
    }
        
    function lex($s, $callback) {
        $tks = $this->getTokenStream($s);
        foreach ($tks as $token) {
            call_user_func_array($callback, $token);
        }
    }
    
    function getTokenStream($s) {
        return new TokenStream($this, $s);
    }
    
    function getAllTokens($s) {
        $ret = [];
        $tks = $this->getTokenStream($s);
        foreach ($tks as $token) {
            $ret[]= $token;
        }
        return $ret;
    }
}


class TokenStream implements \Iterator {
    protected $s;
    
    protected $offset = 0;
    protected $char_offset = 0;
    protected $line_offset = 0;
    protected $line = 1;
    protected $end = false;
    protected $k = 0;
    
    protected $eol = "\n";
    
    protected $cur = null;
    
    protected $back = [];
    
    /**
     * @var Lexer
     */
    protected $lexer;
    
    protected $skips = [];
    
    function __construct($lexer, $s) {
        $this->s = $s;
        $this->lexer = $lexer;
        $this->rewind();
    }
    
    function putBack($token) {
        array_push($this->back, $this->cur);
        $this->cur = $token;
        $this->end = false;
    }

//    function goBack() {
//        $this->end = false;
//        $this->offset-= strlen($this->cur[1]);
//        $this->line = $this->cur[2];
//        $this->char_offset = $this->cur[3];
//    }

    function fetch($prefer = null) {
        $ret = $this->current();
        //try {
            $this->next($prefer);
        //}catch (\Exception $e) {

        //}
        return $ret;
    }
    
    function rewind() {
        $this->offset = 0;
        $this->char_offset = 0;
        $this->line_offset = 0;
        $this->line = 1;
        $this->end = false;
        $this->back = [];
        $this->k = -1;
    }
    
    function current($prefer = null) {
        if ($this->end) {
            return null;
        }
        if ($this->cur === null) {
            $this->next($prefer);
        }
        return $this->cur;
    }
    
    function key() {
        return $this->k;
    }
    
    function next($prefer = null) {
        if ($this->end) {
            return null;
        }
        if (!empty($this->back)) {
            $token = array_pop($this->back);
            $this->cur = $token;
            if ($token === null) {
                $this->end = true;
            }
            return $this->cur;
        }

        $token = $this->lexer->match($this->s, $this->offset, $prefer);
        if ($token === null) {
            if ($this->offset != strlen($this->s)) {
                throw new LexException($this->s[$this->offset], $this->line, $this->char_offset);
            }
            $this->end = true;
            $this->cur = null;
            return null;
        }
        $this->processToken($token);
        return $this->cur;
    }

    protected function processToken($token) {
        list($name, $value) = $token;
        $this->cur = [$name, $value, $this->line, $this->char_offset];
        $this->offset+= strlen($value);
        $lines_added = substr_count($value, $this->eol);
        if ($lines_added > 0) {
            $this->line_offset = $this->offset;
        }
        $this->char_offset = $this->offset - $this->line_offset;
        $this->line+= $lines_added;
        $this->k++;
    }

    function expectToken($name) {
        $token = $this->lexer->expectToken($this->s, $this->offset, $name);
        if ($token === null) {
            throw new LexException($this->s[$this->offset], $this->line, $this->char_offset);
        }
        $this->processToken($token);
        return $this->cur;
    }

    function expectString($str) {
        $result = $this->lexer->expectString($this->s, $this->offset, $str);
        if (!$result) {
            throw new LexException($this->s[$this->offset], $this->line, $this->char_offset);
        }
        $tok = [$str, $str];
        $this->processToken($tok);
        return $this->cur;
    }

    function valid() {
        return !$this->end;
    }
}

class LexException extends \Exception {
    protected $char;
    protected $char_line;
    protected $char_offset;
    
    function getChar() {
        return $this->char;
    }
    
    function getCharLine() {
        return $this->char_line;
    }
    
    function getCharOffset() {
        return $this->char_offset;
    }
    
    function __construct($char, $line, $offset) {
        $this->char = $char;
        $this->char_line = $line;
        $this->char_offset = $offset;
        parent::__construct("unexpected char '".$this->char.
            "' at ".$this->char_offset." of line ".$this->char_line);
    }
    
}
