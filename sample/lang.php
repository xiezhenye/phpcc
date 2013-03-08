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
            '=','+','-','*','/','(',')','{','}',',',
            '==','!=','>','<','>=','<=','<=>',
            'name'=>'[a-zA-Z_][a-zA-Z0-9_]*',
            'func',
        ];
        $rules = [
            'Exps' => [
                [[['+','Exp'] ], true],
            ],
            'Exp' => [
                [['Exp1','nl' ], false],
                [['nl' ], false],
            ],
            'Block' => [
                [['{', 'Exps', '}'], false],
            ],
            'Func' => [
                [['func', 'name', 'ArgList', 'Block'], true],
            ],
            'ArgList' => [
                [ ['(', ['?', 'name', ['*', ',', 'name']] ,')'] , true],
            ],
            'Exp1' => [
                [['name','=','Exp2'], true, 'Set'],
                [['Exp2'], false],
                [['Func'], false],
            ],
            'Exp2' => [
                [['Exp3'], false],
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
        $this->setProcessors();
        $this->setDefaultVars();
    }
    
    function setDefaultVars() {
        $this->addGlobalNativeFunction('sqrt', 'sqrt');
        $this->addGlobalNativeFunction('pow', 'pow');
        $this->addGlobalNativeFunction('print', function($n){
            echo json_encode($n),"\n";
        });
        $this->addGlobalNativeFunction('repr', function($n){
            return json_encode($n);
        });
    }

    function addGlobalNativeFunction($name, $callable) {
        $this->globals[$name] = $this->buildNativeCallable($callable);
    }

    function newContext() {
        return [
            0=>null, // ret, op1
            1=>null, // op2
        ];
    }

    function &currentContext() {
        end($this->ctx);
        return $this->ctx[key($this->ctx)];
    }

    function &currentContextVars() {
        end($this->ctx);
        return $this->ctx[key($this->ctx)];
    }

    function pushContext(&$ctx) {
        $this->ctx[]= &$ctx;
    }

    function popContext() {
        array_pop($this->ctx);
    }
    protected  function binOps(&$ctx, $toks) {
        $this->parseTree($toks[0]);
        $t1 = $ctx[0];
        $this->parseTree($toks[1]);
        $ctx[1] = $ctx[0];
        $ctx[0] = $t1;
    }

    function  setProcessors() {
        $this->processors = [
            'Set'=>function($toks) {
                $ctx = &$this->currentContext();
                $name = $toks[0];
                $this->parseTree($toks[1]);
                $ctx[$name] = $ctx[0];
            },
            'Scala'=>function($toks) {
                $ctx = &$this->currentContext();
                $ctx[0] = $toks[0];
            },
            'Var'=>function($toks) {
                $ctx = &$this->currentContext();
                $name = $toks[0];
                $ctx[0] = isset($ctx[$name]) ? $ctx[$name] : null;
            },
            'Plus'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->binOps($ctx, $toks);
                $ctx[0]+= $ctx[1];
            },
            'Minus'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->binOps($ctx, $toks);
                $ctx[0]-= $ctx[1];
            },
            'Multiply'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->binOps($ctx, $toks);
                $ctx[0]*= $ctx[1];
            },
            'Divide'=>function($toks) {
                $ctx = &$this->currentContext();
                $this->binOps($ctx, $toks);
                $ctx[0]/= $ctx[1];
            },
            'Func' =>function($toks) {
                if (count($toks) == 3) {
                    $al = $toks[1]['tokens'];
                    $code = $toks[2]['tokens'][0];
                    $name = $toks[0];
                } else {
                    $al = $toks[0]['tokens'];
                    $code = $toks[1]['tokens'][0];
                    $name = '';
                }

                $f = ['args'=>$al,'code'=>$code, 'name'=>$name];
                $ctx = &$this->currentContext();
                $ctx[0] = $this->buildCallable($f);
                if ($name != '') {
                    $this->globals[$name] = $ctx[0];
                }
            },
            'Call' => function($toks) {
                $func_name = array_shift($toks);
                if (!isset($this->globals[$func_name])) {
                    return null;
                }
                $func = $this->globals[$func_name];
                $func($toks);
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
                $new_ctx[$arg_name] = $ctx[0];
            }
            $this->pushContext($new_ctx);
            $this->parseTree($func['code']);
            $ctx[0] = $new_ctx[0];
            $this->popContext();
        };
    }

    function buildNativeCallable($func) {
        if (!is_callable($func)) {
            return null;
        }
        return function ($raw_args) use($func) {
            $ctx = &$this->currentContext();
            $args = [];
            foreach ($raw_args as $raw_arg) {
                $this->parseTree($raw_arg);
                $args[]= $ctx[0];
            }
            $ret = call_user_func_array($func, $args);
            $ctx[0] = $ret;
        };
    }

    function execute($code) {
        $this->ctx = [];
        $this->pushContext($this->newContext());
        $ast = $this->parse($code);
        return $this->parseTree($ast);
    }

    function parse($code) {
        //$ast = $this->parser->tree($code, true);
        $ast = ($this->parser->tree($code, false, function(&$t) {
            if ($t[1] === null) {
                return true;
            }
            if ($t[0] == 'd') {
                $t = intval($t[1]);
            } elseif ($t[0] == 'f') {
                $t = floatval($t[1]);
            } elseif ($t[0] == 'name') {
                $t = $t[1];
            } else {
                return false;
            }
            return true;
        }));
        return $ast;
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
        return end($this->ctx)[0];
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

func test(x,y) {
    y*y+x*x
}
print(bar(3))
print(pow(2,10))
print(sqrt(2))
print(test(3,4))

EOF;

try {
    $result = $lang->execute($code);
} catch (Exception $e) {
    echo $e->getMessage();
}

