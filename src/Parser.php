<?php
namespace phpcc;

class LALR1Exception extends \Exception {
    protected $rule1;
    protected $rule2;
    
    function __construct() {

    }
    
    static function conflict($rule1, $rule2) {
        $ret = new LALR1Exception();
        $ret->rule1 = $rule1;
        $ret->rule2 = $rule2;
        $message = "rule conflict: ".json_encode($rule1)." ".json_encode($rule2);
        $ret->message = $message;
        return $ret;
        //parent::__construct($message);
    }
    
    static function invalid($rule) {
        $ret = new LALR1Exception();
        $ret->rule1 = $rule;
        $message = "rule invalid: ".json_encode($rule);
        $ret->message = $message;
        return $ret;
    }
}


abstract class Reducer {
    static $globalCallback;
    static $tempTokens = [];
    abstract function __invoke($name, $tokens);
    
    public static function __set_state($arr) {
        foreach ($arr as $k=>$v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }
}

class RepetitionReducer extends Reducer {
    protected $name;
    protected $max;
    protected $size;
    
    function __construct($name, $max = 0, $size = 1) {
        $this->name = $name;
        $this->max = $max;
        $this->size = $size;
    }
    
    function __invoke($name, $tokens) {
        if (empty($tokens)) { // first *
            self::$tempTokens[$this->name] = [];
            return;
        }
        if ($tokens[0][0] != $name) { // first +
            self::$tempTokens[$this->name] = [$tokens[0]];
        }
        
        for ($i = 1; $i < count($tokens); $i++) { // repitition
            $len = count(self::$tempTokens[$this->name]);
            if ($this->max > 0 && (int)($len / $this->size) >= $this->max) {
                throw new ParseException($tokens[$i]);
            }
            self::$tempTokens[$this->name][]= $tokens[$i];
        }
    }
}

class OrReducer extends Reducer {
    protected $name;
    
    function __construct($name) {
        $this->name = $name;
    }
    
    function __invoke($name, $tokens) {
        self::$tempTokens[$this->name] = $tokens;
    }
}

class MergeReducer extends Reducer {
    protected $oldCallback;
    
    function __construct($cb) {
        $this->oldCallback = $cb;
    }
    
    function __invoke($name, $tokens) {
        $new_tokens = [];
        foreach ($tokens as $token) {
            if (isset(self::$tempTokens[$token[0]])) {
                $new_tokens = array_merge($new_tokens, self::$tempTokens[$token[0]]);
                unset(self::$tempTokens[$token[0]]);
            } else {
                $new_tokens[]= $token;
            }
        }
        if (is_callable($this->oldCallback)) {
            call_user_func($this->oldCallback, $name, $new_tokens);
        } else {
            call_user_func(self::$globalCallback, $name, $new_tokens);
        }
    }
}

class LALR1Builder {
    protected $rules = [];
    protected $first = [];
    
    protected $expanded = [];
    
    protected $reduceFuncs = [];
    
    function __construct($rules) {
        $this->rules = $this->EBNF2BNF($rules);
    }
    
    function getFirst($name) {
        if (isset($this->first[$name])) {
            return $this->first[$name];
        }
        if (!isset($this->rules[$name])) { //final
            return [$name=>$name];
        }
        
        $this->first[$name] = [];
        
        foreach ($this->rules[$name] as $i=>$subrule) {
            list($items, $accept) = $subrule;
            if (empty($items)) {
                continue;
            }
            if (isset($this->rules[$items[0]])) {
                if ($items[0] != $name) {
                    $first_first = $this->getFirst($items[0]);
                    $this->first[$name] = array_merge($this->first[$name], $first_first);
                }
            } else { //final
                $this->first[$name][ $items[0] ] = $items[0];
            }
        }
        return $this->first[$name];
    }
    
    function buildFirst() {
        foreach ($this->rules as $name => $subrules) {
            $this->first[$name] = $this->getFirst($name);
        }
    }
    
    function EBNF2BNF($rules) {
        while (list($name, $subrules) = each($rules)) {
            while (list($ri, $subrule) = each($subrules)) {
                $replaced_subrule = [];
                $need_replace = false;
                foreach ($subrule[0] as $i=>$item) {
                    if (!is_array($item)) {
                        $replaced_subrule[]= $item;
                        continue;
                    }
                    $need_replace = true;
                    $new_name = "$name.$ri.$i'";
                    
                    $action = array_shift($item);
                    switch ($action) {
                    case '*':
                        $f = new RepetitionReducer($new_name);
                        $new_subrule = (array)$item;
                        array_unshift($new_subrule, $new_name);
                        $rules[$new_name] = [[$new_subrule, $f], [[], $f]];
                        break;
                    case '+':
                        $f = new RepetitionReducer($new_name);
                        $new_subrule = (array)$item;
                        array_unshift($new_subrule, $new_name);
                        $rules[$new_name] = [[$new_subrule, $f], [(array)$item, $f]];
                        break;
                    case '?':
                        $f = new RepetitionReducer($new_name);
                        $new_subrule = (array)$item;
                        $rules[$new_name] = [[$new_subrule, $f], [[], $f]];
                        break;
                    case '|':
                        $f = new OrReducer($new_name);
                        $rules[$new_name] = [];
                        foreach ((array)$item as $fork) {
                            $rules[$new_name][]= [(array)$fork, $f];
                        }
                        break;
                    default:
                        if (preg_match('(^(\d*),(\d*)$)', $action, $m)) {
                            $rep_from = intval($m[1] ?: 0);
                            $rep_to = intval($m[2] ?: 0);
                            if ($rep_to != 0 && $rep_to < $rep_from) {
                                throw LALR1Exception::invalid($subrule[0]);
                            }
                        } elseif (preg_match('(^(\d+)$)', $action, $m)) {
                            $rep_from = $rep_to = intval($m[1]);
                        } else {
                            throw LALR1Exception::invalid($subrule[0]);
                        }
                        // var_dump($rep_from, $rep_to);
                        $new_subrule = [];
                        for ($i = 0; $i < $rep_from; $i++) {
                            $new_subrule = array_merge($new_subrule, (array)$item);
                        }
                        $f = new RepetitionReducer($new_name, $rep_to, count($item));
                        $rules[$new_name] = [[$new_subrule, $f]];
                        if ($rep_from != $rep_to) {
                            array_unshift($item, $new_name);
                            $rules[$new_name][] = [$item, $f];
                        }
                    }
                    $replaced_subrule[]= $new_name;
                }
                $mr = $need_replace ? new MergeReducer($subrule[1]) : $subrule[1];
                $rules[$name][$ri] = [$replaced_subrule, $mr, isset($subrule[2])?$subrule[2]:null];
            }
        }
        return $rules;
    }
    
    function stateHash($state) {
        $s = implode('', array_keys($state));
        return md5($s);
    }
    
    function ruleHash($rule) {
        return md5(json_encode([$rule[0],$rule[1],$rule[2]]));
    }
    
    function isFinal($name) {
        return !isset($this->rules[$name]);
    }
    
    function expand($rule_items, $pos) {
        $ret = [];
        if (!isset($rule_items[$pos])) {
            return $ret;
        }
        $name = $rule_items[$pos];
        if ($this->isFinal($name)) {
            return $ret;
        }
        
        $this->expanded[$name] = 1;
        foreach ($this->rules[$name] as $k => $subrule) {
            $next_pos = $pos + 1;
            if (count($rule_items) <= $next_pos) { //final
                $follow = [''=>''];
            } else {
                $next_name = $rule_items[$next_pos];
                $follow = $this->getFirst($next_name);
            }
            
            $new_rule = [$name, $k, 0, $follow];
            $ret[$this->ruleHash($new_rule)]= $new_rule;
            if (count($subrule[0]) == 0) {
                continue;
            }
            $first_item = $subrule[0][0];
            if (!$this->isFinal($first_item)){
                if (!isset($this->expanded[$first_item])) {
                    $this->expanded[$first_item] = 1;
                    $ret = array_merge($ret, $this->expand($subrule[0], 0));
                }
            }
        }
        return $ret;
    }
    
    function root() {
        reset($this->rules);
        return key($this->rules);
    }
    
    function getShiftMap($state_hash) {
        $in_map = [];
        foreach ($this->states[$state_hash][0] as $state_rule) {
            $in = $this->nextItem($state_rule);
            if ($in == '') {
                continue;
            }
            if (!isset($in_map[$in])) {
                $in_map[$in] = [];
            }
            $in_map[$in][] = $state_rule;
        }
        return $in_map;
    }
    
    function nextItem($state_rule) {
        list($rule_name, $rule_index, $rule_pos, $follow) = $state_rule;
        $rule = $this->rules[$rule_name][$rule_index][0];
        if (count($rule) <= $rule_pos) { // at end
            return '';
        }
        $in = $rule[$rule_pos];
        return $in;
    }
    
    function ruleFromStateRule($state_rule) {
        $rule = $this->rules[$state_rule[0]][$state_rule[1]];
        return $rule;
    }
    
    function shiftStateRule($state_rule) {
        list($rule_name, $rule_index, $rule_pos, $follow) = $state_rule;
        $rule = $this->ruleFromStateRule($state_rule);
        
        $new_pos = $rule_pos + 1;
        $next = isset($rule[0][$new_pos]) ? $rule[0][$new_pos] : '';
        $next2 = isset($rule[0][$new_pos + 1]) ? $rule[0][$new_pos + 1] : '';

        if (isset($this->rules[$next2])) {
            $new_follow = array_merge($follow, $this->first[$next2]);
        } else { //final
            $new_follow = array_merge($follow, [$next2=>$next2]);
        }
        $new_rule = [$rule_name, $rule_index, $new_pos, $new_follow];
        return $new_rule;
    }
    
    function build() {
        $this->buildFirst();
        $stack = [];
        
        $root_rule = $this->root();
        $this->expanded = [];
        $init_state = [$this->expand([$root_rule], 0), []];
        $hash = $this->stateHash($init_state[0]);
        $this->states[$hash] = $init_state;
        
        $stack[]= $hash;
        
        while (!empty($stack)) {
            $cur_hash = array_pop($stack);
            $in_map = $this->getShiftMap($cur_hash);
            foreach ($in_map as $in=>$state_rules) {
                $new_state_rules = [];
                $this->expanded = [];
                $follow_to_spread = [];
                foreach ($state_rules as $state_rule) {
                    $new_state_rule = $this->shiftStateRule($state_rule);
                    
                    $rule_hash = $this->ruleHash($new_state_rule);
                    
                    $follow_to_spread[$rule_hash]= $new_state_rule[3];
                    
                    $new_state_rules[$rule_hash]= $new_state_rule;
                    
                    $rule_items = $this->rules[$new_state_rule[0]][$new_state_rule[1]][0];
                    $expanded_rules = $this->expand($rule_items, $new_state_rule[2]);
                    $new_state_rules = array_merge($new_state_rules, $expanded_rules);
                }
                $new_hash = $this->stateHash($new_state_rules);

                if (!isset($this->states[$new_hash])) {
                    $this->states[$new_hash] = [$new_state_rules, []];
                    $stack[]= $new_hash;
                } else {
                    $this->spreadFollow($new_hash, $follow_to_spread);
                }
                $this->states[$cur_hash][1][$in] = $new_hash; //test
            }
        }
        return $this->states;
    }
    
    function spreadFollow($target_state_hash, $follow_to_spread) {
        $old_state_rules = $this->states[$target_state_hash][0];
        foreach ($follow_to_spread as $rule_hash => $follow) {
            if (!isset($old_state_rules[$rule_hash])) {
                continue;
            }
            $old_follow = $old_state_rules[$rule_hash][3];
            $new_follow = array_merge($old_follow, $follow);
            if ($old_follow == $new_follow) {
                continue;
            }
            $this->states[$target_state_hash][0][$rule_hash][3] = $new_follow;
            $next = $this->nextItem($old_state_rules[$rule_hash]);
            if ($next == '') {
                continue;
            }
            if (!isset($this->states[$target_state_hash][1][$next])) {
                continue;
            }
            $next_state_hash = $this->states[$target_state_hash][1][$next];
            $this->spreadFollow($next_state_hash, $new_follow);
        }
    }
    
    function optimize() {
        $ret = [];
        $hash_map = [];
        foreach ($this->states as $hash=>$state) {
            $reduce_rules = [];
            $reduce_map = [];
            foreach ($state[0] as $state_rule) {
                $name = $state_rule[0];
                $rule = $this->rules[$name][$state_rule[1]];
                
                if (count($rule[0]) > $state_rule[2]) { //not reduce
                    continue;
                }
                $alias = isset($rule[2]) ? $rule[2] : $name;
                $reduce_rules[]= [$name, $rule[0], $rule[1], $alias];
                $rule_id = count($reduce_rules) - 1;
                foreach ($state_rule[3] as $tok) {
                    if (isset($reduce_map[$tok])) {
                        $conflicted = $reduce_rules[$reduce_map[$tok]];
                        throw LALR1Exception::conflict([$name=>$rule[0]], [$conflicted[0]=>$conflicted[1]]);
                    }
                    $reduce_map[$tok] = $rule_id;
                }
            }
            $ret[]= [$reduce_rules, $reduce_map, []];
            $hash_map[$hash] = count($ret) - 1;
        }
        foreach ($this->states as $hash=>$state) {
            $id = $hash_map[$hash];
            foreach ($state[1] as $tok=>$next_hash) {
                if (isset($ret[$id][1][$tok])) {
                    unset($ret[$id][1][$tok]);
                }
                $next_id = $hash_map[$next_hash];
                $ret[$id][2][$tok] = $next_id;
            }
        }
        return $ret;
    }
}

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

