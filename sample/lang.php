<?php
include '../src/phpcc.php';

class Lang {
    protected $parser = null;
    protected $ctx = [];
    protected $globals = [];

    function __construct() {
        $tokens = [
            'sp'=>'[ \t]',
            'comment'=>'#.*',
            'd'=>'[1-9][0-9]*',
            'f'=>'[0-9]+\.[0-9]+',
            'nl'=>'[\n]+',
            '+','-','*','/','(',')','{','}','=',
            '==','!=','>','<','>=','<=','<=>',
            ',',
            'name'=>'[a-zA-Z_][a-zA-Z0-9_]*',
            'func',
        ];
        $rules = [
            'Prog' => [
                [[ 'Exps' ], false],
            ],
            'Exps' => [
                [[['+','Exp'] ], false],
            ],

            'Exp' => [
                [['Exp1','nl' ], false],
                [['nl' ], false],
            ],
            'Block' => [
                [['{', 'Exps', '}'], true],
            ],
            'Func' => [
                [['func', ['?','name'], 'ArgList', 'Block'], true],
            ],
            'ArgList' => [
                [ ['(', 'name', ['*', ',', 'name'] ,')'] , true],
            ],

            'Exp1' => [
                [['name','=','Exp2'], true, 'Set'],
                [['Exp2'], false],
                [['Func'], false],
            ],
            'Exp2' => [
                [['Exp3'], true],
                [['Call'], false],
            ],

            'Call' => [
                [['name', '(',['?', 'Exp2',['*', ',', 'Exp2']],')'], true],
            ],
            'Exp3' => [
//                [['Exp3','==','Exp4'], true, 'Eq'],
//                [['Exp3','!=','Exp4'], true, 'Ne'],
//                [['Exp3','<=','Exp4'], true, 'Le'],
//                [['Exp3','>=','Exp4'], true, 'Ge'],
//                [['Exp3','<','Exp4'], true, 'Lt'],
//                [['Exp3','>','Exp4'], true, 'Gt'],
//                [['Exp3','<=>','Exp4'], true, 'Cmp'],
                [['Exp4'], false],
            ],
            'Exp4' => [
                [['Exp4','+','Exp5'], true, 'Plus'],
                [['Exp4','-','Exp5'], true, 'Minus'],
                [['Exp5'], false],
            ],
            'Exp5' => [
                [['Exp5','*','Exp6'], true, 'Multiply'],
                [['Exp5','/','Exp6'], true, 'Divide'],
                [['Exp6'], false],
            ],
            'Exp6' => [
                [['-', 'Exp6'], true, 'Reverse'],
                [['Value'], false],
                [['(','Exp2',')'], false],
            ],
            'Value' => [
                [['Var'], false],
                [['Scala'], false],
            ],
            'Scala' => [
                [['d'], true],
                [['f'], true],
            ],
            'Var' => [
                [['name'], true],
            ],
        ];
        $lexer = new phpcc\Lexer($tokens);
        $parser = new phpcc\Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp','comment']);
        $this->parser = $parser;
        $this->setDefaultVars();
        $this->setProcessors();

    }
    
    function setDefaultVars() {
        $this->globals['square'] = $this->buildLocalCallable(function($n){
            return $n*$n;
        });
        $this->globals['pow'] = $this->buildLocalCallable(function($n){
            return pow($n[0], $n[1]);
        });

        $this->globals['repr'] = $this->buildLocalCallable(function($n){
            return json_encode($n);
        });
        $this->globals['print'] = $this->buildLocalCallable(function($n){
            echo json_encode($n),"\n";
        });
    }

    function newContext() {
        return [
            'vars'=>[],
            't0'=>null, // ret, op1
            't1'=>null, // op2
        ];
    }

    function &currentContext() {
        end($this->ctx);
        return $this->ctx[key($this->ctx)];
    }

    function &currentContextVars() {
        end($this->ctx);
        return $this->ctx[key($this->ctx)]['vars'];
    }

    function pushContext(&$ctx) {
        $this->ctx[]= &$ctx;
    }

    function popContext() {
        array_pop($this->ctx);
    }

    function  setProcessors() {
        $this->processors = [
            'Set'=>function($toks) {
                $ctx = &$this->currentContext();
                $name = $toks[0][1];
                $this->parseTree($toks[2]);
                $ctx['vars'][$name] = $ctx['t0'];
            },
            'Scala'=>function($toks) {
                $ctx = &$this->currentContext();
                if ($toks[0][0] == 'd') {
                    $ctx['t0'] = intval($toks[0][1]);
                } elseif ($toks[0][0] == 'f') {
                    $ctx['t0'] = floatval($toks[0][1]);
                }
            },
            'Var'=>function($toks) {
                $ctx = &$this->currentContext();
                $name = $toks[0][1];
                $ctx['t0'] = isset($ctx['vars'][$name]) ? $ctx['vars'][$name] : null;
            },
            'Plus'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->parseTree($toks[0]);
                $ctx['t1'] = $ctx['t0'];
                $this->parseTree($toks[2]);
                $ctx['t0']+= $ctx['t1'];
            },
            'Minus'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->parseTree($toks[0]);
                $ctx['t1'] = $ctx['t0'];
                $this->parseTree($toks[2]);
                $ctx['t0']-= $ctx['t1'];
            },
            'Multiply'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->parseTree($toks[0]);
                $ctx['t1'] = $ctx['t0'];
                $this->parseTree($toks[2]);
                $ctx['t0']*= $ctx['t1'];
            },
            'Divide'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->parseTree($toks[0]);
                $ctx['t1'] = $ctx['t0'];
                $this->parseTree($toks[2]);
                $ctx['t0']/= $ctx['t1'];
            },
            'Func' =>function($toks) {
                //['func', ['?','name'], 'ArgList', 'Block']
                if (count($toks) == 4) {
                    $al = $toks[2]['tokens'];
                    $code = $toks[3]['tokens'][1];
                    $name = $toks[1][1];
                } else {
                    $al = $toks[1]['tokens'];
                    $code = $toks[2]['tokens'][1];
                    $name = '';
                }

                $f = ['args'=>[],'code'=>$code, 'name'=>$name];
                for ($i = 1; $i < count($al); $i+= 2) {
                    $f['args'][]= $al[$i][1];
                }
                $ctx = &$this->currentContext();
                $ctx['t0'] = $this->buildCallable($f);
                if ($name != '') {
                    $this->globals[$name] = $ctx['t0'];
                }
            },
            'Call' => function($toks) {
                $func_name = $toks[0][1];
                if (!isset($this->globals[$func_name])) {
                    return null;
                }
                $func = $this->globals[$func_name];
                $raw_args = [];
                for ($i = 2; $i < count($toks); $i+= 2) {
                    $raw_args[]= $toks[$i];
                }
                $func($raw_args);
            }
        ];
    }

    function buildCallable($func) {
        return function ($raw_args) use($func) {
            if (count($func['args']) != count($raw_args)) {
                return null;
            }
            $ctx = &$this->currentContext();
            $new_ctx = $this->newContext();
            foreach ($func['args'] as $i=>$arg_name) {
                $this->parseTree($raw_args[$i]);
                $new_ctx['vars'][$arg_name] = $ctx['t0'];
            }
            $this->pushContext($new_ctx);
            $this->parseTree($func['code']);
            $ctx['t0'] = $new_ctx['t0'];
            $this->popContext();
        };
    }

    function buildLocalCallable($func) {
        if (!is_callable($func)) {
            return null;
        }
        return function ($raw_args) use($func) {
            $ctx = &$this->currentContext();
            $args = [];
            foreach ($raw_args as $raw_arg) {
                $this->parseTree($raw_arg);
                $args[]= $ctx['t0'];
            }
            $ret = call_user_func_array($func, $args);
            $ctx['t0'] = $ret;
        };
    }

    function execute($code) {
        $ast = $this->parser->tree($code, true);
        $this->pushContext($this->newContext());
        return $this->parseTree($ast);
    }

    function parseTree($ast) {
        //lazy parse
        $tokens = $ast['tokens'];
        $name = $ast['name'];
        if (!isset($this->processors[$name])) {
            //default processor: parse immediately
            foreach ($tokens as $item) {
                if (!isset($item['name'])) {
                    continue; //final
                }
                $this->parseTree($item);
            }
        } else {
            $processor = $this->processors[$name];
            $processor($tokens);
        }
        return end($this->ctx)['t0'];
    }
}

/////////////////////////////////////////////////////////////


$lang = new Lang();

$code = <<<'EOF'

a=1

b=2+1
c=(a+b)*10
print(c)
func foo(a) {
    a*a
}
d = foo(5)
print(d)
func bar(a){
 b=a
 b = b * b
 a+b
}
print(bar(3))

EOF;

try {
    $result = $lang->execute($code);
} catch (Exception $e) {
    echo $e->getMessage();
}

