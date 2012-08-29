<?php
namespace phpcc;

class Lexer {
    protected $names = [];
    protected $patterns = [];
    protected $groupOffsets = [];
    
    function __construct($m = null) {
        if (empty($m)) {
            return;
        }
        $this->init($m);
    }
    
    function init($m) {
        $patterns = [];
        $this->patterns = [];
        $this->groupOffsets = [];
        $offset = 0;
        $i = 0;
        foreach ($m as $name=>$regex) {
            $patterns[]= "($regex)";
            $this->names[]= $name;
            $offset+= $this->countGroups($regex) + 1;
            $this->groupOffsets[$offset]= $i;
            $i++;
        }
        $count = count($patterns);
        for ($i = 0; $i < $count; $i++) {
            $this->patterns[$i] = '('.implode('|', array_slice($patterns, $i)).')SAms';
        }
    }
    
    function setPatterns($patterns) {
        $this->pattern = $patterns;
    }
    
    function getPatterns($patterns) {
        return $this->patterns;
    }
    
    function countGroups($pattern) {
        $gp = '/(?<!\\\\)(?:\\\\\\\\)*\((?!\?)/S';
        $ret = preg_match_all($gp, $pattern);
        return $ret;
    }
    
    function match($s, $offset) {
        $name = $value = '';
        $cur = 0;
        $pattern = $this->patterns[$cur];
        while (preg_match($pattern, $s, $m, null, $offset)) {
            $v = end($m);
            $group = key($m) + $cur;
            $pattern_id = $this->groupOffsets[$group];
            if (strlen($v) > strlen($value)) {
                $value = $v;
                $name = $this->names[$pattern_id];
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
        $token = $this->lexer->match($this->s, $this->offset);
        if (empty($token)) {
            if ($this->offset != strlen($this->s)) {
                throw new \Exception("unexpected char ".$this->s[$this->offset].
                                    " at ".$this->char_offset." of line ".$this->line);
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

