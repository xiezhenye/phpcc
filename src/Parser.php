<?php
namespace phpcc;



class LALR1Builder {
    protected $rules = [];
    protected $first = [];
    
    protected $expanded = [];
    
    function __construct($rules) {
        $this->rules = $rules;
    }
    
    function getFirst($name) {
        if (isset($this->first[$name])) {
            return $this->first[$name];
        }
        if (!isset($this->rules[$name])) { //final
            return [$name];
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
    
    function shiftStateRule($state_rule) {
        list($rule_name, $rule_index, $rule_pos, $follow) = $state_rule;
        $rule = $this->rules[$rule_name][$rule_index];
        
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
                if (isset($this->states[$cur_hash][1][$in])) {
                    throw new Exception("shift shift conlict");
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
                        
                        throw new Exception("reduce-reduce conflict: ".
                                json_encode($reduce_rules[$reduce_map[$tok]]). ' '.
                                json_decode($reduce_rules[$reduce_map[$rule_id]]));
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
                    throw new Exception("shift-reduce conflict");
                }
                $next_id = $hash_map[$next_hash];
                $ret[$id][2][$tok] = $next_id;
            }
            
        }
        return $ret;
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
    
    function init($rules) {
        $builder = new LALR1Builder($rules);
        $builder->build();
        $this->states = $builder->optimize();
        reset($rules);
        $this->root = key($rules);
        //var_dump($this->states);
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
    
    function parse($s, $callback) {
        $token_stack = [];
        $state_stack = [0];
        $tokens = $this->lexer->getTokenStream($s);
        $back_token = null;
        
        $token = $this->nextToken($tokens, $back_token);
        
        while (!empty($state_stack)) {
            $cur_id = end($state_stack);
            $cur = $this->states[$cur_id];
            
            if (isset($cur[2][$token[0]])) { //shift
                $token_stack[]= $token;
                $state_stack[]= $cur[2][$token[0]];
                $token = $this->nextToken($tokens, $back_token);
            } else { //reduce
                if (isset($cur[1][$token[0]])) { 
                    $rule = $cur[0][$cur[1][$token[0]]];
                } elseif (isset($cur[1][''])) { 
                    $rule = $cur[0][$cur[1]['']];
                } else {
                    if (!is_null($back_token)) {
                        $token = $back_token;
                    }
                    throw new \Exception("unexpected {$token[0]} {$token[1]} at {$token[2]}:{$token[3]}");
                }
                $reduced_tokens = [];
                for ($i = count($rule[1]) - 1; $i >= 0; $i--) {
                    $name = $rule[1][$i];
                    $top_token = array_pop($token_stack);
                    if ($name != $top_token[0]) {
                        throw new \Exception("!!!!!");
                    }
                    $reduced_tokens[]= $top_token;
                    array_pop($state_stack);
                }
                
                
                $back_token = $token;
                $token = [$rule[0], '', $top_token[2], $top_token[3]];
                
                if ($rule[2]) {
                    $reduced_tokens = array_reverse($reduced_tokens);
                    $callback($rule[3], $reduced_tokens);
                }
                if ($rule[0] == $this->root && $back_token == null) {
                    break;
                }
            }
            
        }
        if (!empty($token_stack)) {
            throw new \Exception("unexpected EOF");
        }
    }
}

