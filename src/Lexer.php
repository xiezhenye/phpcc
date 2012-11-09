<?php
namespace phpcc;

class Lexer {
    protected $names = [];
    protected $patterns = [];
    protected $groupOffsets = [];
    protected $keywords = [];
    
    function __construct($m = null, $key_sensitive = true) {
        if (empty($m)) {
            return;
        }
        $this->init($m, $key_sensitive);
    }
    
    function init($m, $key_sensitive = true) {
        $patterns = [];
        $this->patterns = [];
        $this->groupOffsets = [];
        $offset = 0;
        $i = 0;
        foreach ($m as $name=>$regex) {
            if (is_int($name)) {
                $this->names[$i]= $regex;
                $regex = preg_quote($regex);
                $this->keywords[$regex] = true;
            } else {
                $this->names[$i]= $name;
            }
            $patterns[$i]= "($regex)";
            
            for ($j = 0; $j < $this->countGroups($regex) + 1; $j++) {
                $offset++; 
                $this->groupOffsets[$offset]= $i;
            }
            $i++;
        }
        $count = count($patterns);
        $flag = $key_sensitive ? 'SAm' : 'SAmi';
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
    
    function match($s, $offset) {
        $name = $value = '';
        $cur = 0;
        $pattern = $this->patterns[0];
        while (preg_match($pattern, $s, $m, null, $offset)) {
            $v = $m[0];
            end($m);
            $group = key($m) + $cur;
            $pattern_id = $this->groupOffsets[$group];
            $n = $this->names[$pattern_id];
            if (strlen($v) > strlen($value) ||
                $v === $value && isset($this->keywords[$n])) {
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
    
    protected $cur;
    
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
        array_push($this->back, $token);
    }
    
    function fetch() {
        $ret = $this->current();
        $this->next();
    }
    
    function rewind() {
        $this->offset = 0;
        $this->char_offset = 0;
        $this->line_offset = 0;
        $this->line = 1;
        $this->end = false;
        $this->k = 0;
        $this->next();
    }
    
    function current() {
        if ($this->end) {
            return null;
        }
        return $this->cur;
    }
    
    function key() {
        return $this->k;
    }
    
    function next() {
        if ($this->end) {
            return null;
        }
        if (empty($this->back)) {
            $token = $this->lexer->match($this->s, $this->offset);
        } else {
            $token = array_pop($this->back);
        }
        if (empty($token)) {
            if ($this->offset != strlen($this->s)) {
                throw new LexException($this->s[$this->offset], $this->line, $this->char_offset);
            }
            $this->end = true;
            return null;
        }
        
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
