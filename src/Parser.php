<?php
namespace phpcc;

class ParseException extends \Exception {
    protected $char;
    protected $name;
    protected $char_line;
    protected $char_offset;
    
    function __construct($token) {
        $this->value = $token[1];
        $this->name = $token[0];
        $this->char_line = $token[2];
        $this->char_offset = $token[3];
        if ($this->name == '') {
            $msg = "unexpected EOF";
        } else {
            $msg = "unexpected {$this->name} {$this->value} at {$this->char_line}:{$this->char_offset}";
        }
        parent::__construct($msg);
    }
}

class Parser {
    protected $lexer;
    protected $states = [];
    
    protected $skipTokens = [];
    protected $root;
    
    function setLexer($lexer) {
        $this->lexer = $lexer;
    }
    
    function setSkipTokens($names) {
        $this->skipTokens = array_flip($names);
    }
    
    function dump() {
        return [$this->states, $this->skipTokens, $this->root];
    }
    
    function load($data) {
        list($this->states, $this->skipTokens, $this->root) = $data;
    }
    
    function init($rules) {
        $builder = new LALR1Builder($rules);
        $states = $builder->build();
        $this->states = $builder->optimize();
        reset($rules);
        $this->root = key($rules);
    }
    
    function nextToken($tokens, &$back) {
        if (!is_null($back)) {
            $ret = $back;
            $back = null;
            return $ret;
        }
        $ret = $tokens->current();
        if (is_null($ret)) {
            return $ret;
        }
        while (isset($this->skipTokens[$ret[0]])) {
            $ret = $tokens->next();
        }
        $tokens->next();
        return $ret;
    }
    
    function parse($s, $callback, $force = false) {
        $token_stack = [];
        $state_stack = [0];
        $p_token_stack = 0;
        $p_state_stack = 1;
        
        $tokens = $this->lexer->getTokenStream($s);
        $back_token = null;
        
        Reducer::$globalCallback = $callback;
        
        $token = $this->nextToken($tokens, $back_token);
        
        while ($p_state_stack > 0) {
            $cur_id = $state_stack[$p_state_stack - 1];
            $cur = $this->states[$cur_id];
            
            if (isset($cur[2][$token[0]])) { //shift
                $token_stack[$p_token_stack++] = $token;
                $state_stack[$p_state_stack++]= $cur[2][$token[0]];
                
                $token = $this->nextToken($tokens, $back_token);
            } else { //reduce
                // ensure can reduce
                if (isset($cur[1][$token[0]])) { 
                    $rule = $cur[0][$cur[1][$token[0]]];
                } elseif (isset($cur[1][''])) { 
                    $rule = $cur[0][$cur[1]['']];
                } else {
                    if (!is_null($back_token)) {
                        $token = $back_token;
                    }
                    throw new ParseException($token);
                }
                
                $reduced_tokens = [];
                $p_end = $p_state_stack;
                for ($i = count($rule[1]) - 1; $i >= 0; $i--) {
                    $name = $rule[1][$i];
                    $top_token = $token_stack[--$p_token_stack];
                    
                    if ($name != $top_token[0]) {
                        throw new \Exception("!!!!!");
                    }
                    $p_state_stack--;
                }
                
                
                $back_token = $token;
                if (empty($top_token)) {
                    $token = [$rule[0], null, null, null];
                } else {
                    $token = [$rule[0], null, $top_token[2], $top_token[3]];
                }
                //
                if ($rule[2]) {
                    $reduced_tokens = array_slice($token_stack, $p_state_stack - 1, $p_end - $p_state_stack);
                    if ($force && !($rule[2] instanceof Reducer)) {
                        $callback($rule[3], $reduced_tokens);
                    } elseif (is_callable($rule[2])) {
                        $rule[2]($rule[3], $reduced_tokens);
                    } else {
                        $callback($rule[3], $reduced_tokens);
                    }
                }
                if ($rule[0] == $this->root && $back_token == null) {
                    break;
                }
            }
            
        }
        if ($p_token_stack != 0) {
            throw new ParseException($token);
        }
    }
    
    function tree($expression) {
        $stack = [];
        $this->parse($expression, function($name, $tokens) use (&$stack) {
            $r = ['name'=>$name];
            $t = [];
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                $token = $tokens[$i];
                if (is_null($token[1])) {
                    $t[]= array_pop($stack);
                } else { //final
                    $t[]= $token;
                }
            }
            $r['tokens'] = array_reverse($t);
            array_push($stack, $r);
        }, true);
        return $stack[0];
    }
    
    function printTree($expression, $verbos = false) {
        $tree = $this->tree($expression);
        $this->_printTree($tree, 0, $verbos);
    }
    
    function _printTree($tree, $d, $verbos) {
        if (isset($tree['name'])) {
            echo str_repeat('  ', $d), $tree['name']."\n";
            foreach ($tree['tokens'] as $node) {
                $this->_printTree($node, $d+1, $verbos);
            }
        } else {
            echo str_repeat('  ', $d), "[{$tree[0]}: {$tree[1]}",
                $verbos ? " @{$tree[2]}.{$tree[3]}" : ''           ,"]\n";
        }
    }
}


