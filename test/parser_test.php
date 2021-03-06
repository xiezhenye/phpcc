<?php
namespace phpcc;

error_reporting(E_ALL);
include '../src/phpcc.php';

class ParserTest extends \PHPUnit_Framework_TestCase {
    function setUp() {

    }

    function testHasEmpty() {
        $builder = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
            ]
        ]);
        $this->assertFalse($builder->hasEmpty('A'));
        $builder = new LALR1Builder([
            'A'=>[
                [[], true],
                [['d'], true],
            ]
        ]);
        $this->assertTrue($builder->hasEmpty('A'));

        $builder = new LALR1Builder([
            'A'=>[
                [[], true],
            ]
        ]);
        $this->assertTrue($builder->hasEmpty('A'));

        $builder = new LALR1Builder([
            'A'=>[
                [['B'], true],
            ],
            'B'=>[
                [[], true],
                [['a'], true],
            ]
        ]);
        $this->assertTrue($builder->hasEmpty('B'));
        $this->assertTrue($builder->hasEmpty('A'));

        $builder = new LALR1Builder([
            'A'=>[
                [['A','a'], true],
                [[], true],
            ]
        ]);
        $this->assertTrue($builder->hasEmpty('A'));

    }

    function testFirst() {
        $builder = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $f = $builder->getFirst('A');
        $this->assertEquals(['d'=>'d','('=>'(',], $f);
        
        $f = $builder->getFirst('d');
        $this->assertEquals(['d'=>'d'], $f);
        
        $f = $builder->getFirst('(');
        $this->assertEquals(['('=>'('], $f);
        
        $builder = new LALR1Builder([
            'A'=>[
                [['d'], true],
                [['B'], true],
              ],
            'B'=>[
                [['f'], true],
              ]
        ]);
        $builder->buildHasEmpty();
        $f = $builder->getFirst('A');
        $this->assertEquals(['d'=>'d','f'=>'f',], $f);
        $f = $builder->getFirst('B');
        $this->assertEquals(['f'=>'f',], $f);
    }
    
    function testIsFinal() {
        $builder = new LALR1Builder([
            'A'=>[
                  [['(','A',')'], true],
                  [['d'], true],
                ]
        ]);
        $this->assertTrue($builder->isFinal('d'));
        $this->assertTrue($builder->isFinal('('));
        $this->assertFalse($builder->isFinal('A'));
    }
    
    function testRoot() {
        $builder = new LALR1Builder([
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $this->assertEquals('A', $builder->root());
        
        $builder = new LALR1Builder([
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            
        ]);
        $this->assertEquals('B', $builder->root());
    }
    
    function testRuleHash() {
        $builder = new LALR1Builder([]);
        $rule = ['A',0,0];
        $hash = $builder->ruleHash($rule);
        $this->assertEquals(md5('["A",0,0]'), $hash);
    }
    
    function testShift() {
        $builder = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
        ]);
        $result = $builder->shiftStateRule(['A',0,0,[''=>'']]);
        $this->assertEquals(4, count($result));
        $this->assertEquals('A', $result[0]);
        $this->assertEquals(0, $result[1]);
        $this->assertEquals(1, $result[2]);
        $this->assertEquals([''=>'',')'=>')'], $result[3]);
    }
    
    function testState() {
        $builder = new LALR1Builder([
            'A'=>[
                [['d'], true],
              ],
        ]);
        $states = $builder->build();
        $this->assertEquals(2, count($states));
        $states2 = $builder->optimize();
        $this->assertEquals(2, count($states2));
        //var_dump($states2[1][2]);
        $this->assertEquals(1, $states2[0][2]['d']);
        $this->assertEquals(0, count($states2[1][2]));
        $this->assertEquals(0, count($states2[0][0]));
        $this->assertEquals(0, count($states2[0][1]));
        $this->assertEquals([''=>0], $states2[1][1]);
        $this->assertEquals(1, count($states2[1][0]));
        $this->assertEquals(['A',['d'], true,'A'], $states2[1][0][0]);
    }
    
    function testEmpty() {
        $rules = [
            'A'=>[
                [['d'], true],
                [[], true],
              ],
        ];
        $builder = new LALR1Builder($rules);
        $states = $builder->build();
        $this->assertEquals(2, count($states));
        $states2 = $builder->optimize();
        $this->assertEquals(2, count($states2));
        $this->assertEquals(1, $states2[0][2]['d']);
        $this->assertEquals(1, count($states2[0][1]));

        $rules = [
            'A'=>[
                [['A','d'], true],
                [[], true],
            ],
        ];
        $builder = new LALR1Builder($rules);
        $states = $builder->build();
        $this->assertEquals(3, count($states));
        $states2 = $builder->optimize();
        $this->assertEquals(3, count($states2));
        $this->assertEquals(0, $states2[0][1]['']);
        $this->assertEquals(1, $states2[0][2]['A']);
        $this->assertEmpty($states2[1][1]);
        $this->assertEquals(2, $states2[1][2]['d']);
        $this->assertEmpty($states2[2][2]);
        $this->assertEquals(0, $states2[2][1]['']);


        $tokens = [
            'd'=>'[0-9]+',
            'foo','bar',';',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 'B', ';'], true],
            ],
            'B'=>[
                [[ 'B','d' ], true],
                [[  ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse(";", function($rule, $tokens){
        });
        $parser->parse("123;", function($rule, $tokens){
        });
        $parser->parse("123 789;", function($rule, $tokens){
        });
        $parser->parse("123 789 0;", function($rule, $tokens){
        });
    }
    
    function testLALRException() {
        $builder = new LALR1Builder([
            'A'=>[
                [['A','a'], true],
                [['A','a'], true],
            ],
        ]);
        try {
            $builder->build();
            $builder->optimize();
            $this->fail();
        } catch (LALR1Exception $e) {
            //$this->assertEquals(LALR1Exception::REDUCE_REDUCE_CONFLICT, $e->getCode());
        }
    }
    
    
    function testEBNF() {
        $rules = [
            'A'=>[
                [[ ['*','d'] ], true],
            ]
        ];
        $ret = (new PreProcessor)->parse($rules);
        $this->assertArrayHasKey("A.0.0~", $ret);
        
    }
    
    function testRep1() {
        $tokens = [
            'd'=>'[0-9]+',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ ['*','d'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123 456 789", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(3, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('d', $tokens[1][0]);
            $this->assertEquals('456', $tokens[1][1]);
            $this->assertEquals('d', $tokens[2][0]);
            $this->assertEquals('789', $tokens[2][1]);
        });
        $parser->parse("123", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(1, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
        });
        
        $parser->parse("", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(0, $tokens);
        });




    }
    
    function testRep3() {
        $tokens = [
            'd'=>'[0-9]+',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ ['3', 'd', 'd'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123 456 789 123 456 789", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(6, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('d', $tokens[1][0]);
            $this->assertEquals('456', $tokens[1][1]);
            $this->assertEquals('d', $tokens[5][0]);
            $this->assertEquals('789', $tokens[5][1]);
        });
        try {
            $parser->parse("123 456 123 456 123 456 123", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        try {
            $parser->parse("123 456 123 456 123 456 123 456", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        try {
            $parser->parse("123 456 789 012", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        
        $rules = [
            'A'=>[
                [[ ['2,4', 'd'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123 456 789", function($rule, $tokens){
            $this->assertCount(3, $tokens);
        });
        $parser->parse("123 456", function($rule, $tokens){
            $this->assertCount(2, $tokens);
        });
        $parser->parse("123 456 789 012", function($rule, $tokens){
            $this->assertCount(4, $tokens);
        });
        try {
            $parser->parse("123", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        try {
            $parser->parse("123 456 123 456 123", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        
        $rules = [
            'A'=>[
                [[ ['3,', 'd'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123 123 123 123", function($rule, $tokens){
            $this->assertCount(4, $tokens);
        });
        $parser->parse("123 456 789", function($rule, $tokens){
            $this->assertCount(3, $tokens);
        });
        
        try {
            $parser->parse("123 456", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        try {
            $parser->parse("", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
  
        $rules = [
            'A'=>[
                [[ [',3', 'd'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123", function($rule, $tokens){
            $this->assertCount(1, $tokens);
        });
        $parser->parse("123 456 789", function($rule, $tokens){
            $this->assertCount(3, $tokens);
        });
        $parser->parse("", function($rule, $tokens){
            $this->assertCount(0, $tokens);
        });
        
        try {
            $parser->parse("123 456 789 012", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        
        $rules = [
            'A'=>[
                [[ ['3,2', 'd'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        try {
            $parser->init($rules);
            $this->fail();
        } catch (LALR1Exception $e) {
            //echo $e->getMessage();
        }
    }


    function testOr() {
        $tokens = [
            'd'=>'[0-9]+',
            'a',
            'b',
            'c',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 'd', ['|','a','b'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123a", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(2, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('a', $tokens[1][0]);
        });
        $parser->parse("123 b", function($rule, $tokens){
            $this->assertCount(2, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('b', $tokens[1][0]);
        });
        try {
            $parser->parse("123", function($rule, $tokens){
                $this->fail();
            });
        } catch (ParseException $e) {
            //
        }
    }
    
    function testOpt() {
        $tokens = [
            'd'=>'[0-9]+',
            's'=>'[a-z]+',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 's', ['?','d'], 's' ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("abc 123 xxx", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(3, $tokens);
            $this->assertEquals('s', $tokens[0][0]);
            $this->assertEquals('abc', $tokens[0][1]);
            $this->assertEquals('d', $tokens[1][0]);
            $this->assertEquals('123', $tokens[1][1]);
            $this->assertEquals('s', $tokens[2][0]);
            $this->assertEquals('xxx', $tokens[2][1]);
        });
        
        $parser->parse("abc xxx", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(2, $tokens);
            $this->assertEquals('s', $tokens[0][0]);
            $this->assertEquals('abc', $tokens[0][1]);
            $this->assertEquals('s', $tokens[1][0]);
            $this->assertEquals('xxx', $tokens[1][1]);
        });
        try {
            $parser->parse("abc 123 321 xxx", function($rule, $tokens){
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
    }
    
    function testOptRep() {
        $tokens = [
            'd'=>'[0-9]+',
            'a',
            'b',
            'c',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 'd', ['+',['|','a','b']] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123aba", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(4, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('a', $tokens[1][0]);
            $this->assertEquals('b', $tokens[2][0]);
            $this->assertEquals('a', $tokens[3][0]);
        });
    }
    
    function testRep2() {
        $tokens = [
            'd'=>'[0-9]+',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ ['+','d'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("123 456 789", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(3, $tokens);
            $this->assertEquals('d', $tokens[0][0]);
            $this->assertEquals('123', $tokens[0][1]);
            $this->assertEquals('d', $tokens[1][0]);
            $this->assertEquals('456', $tokens[1][1]);
            $this->assertEquals('d', $tokens[2][0]);
            $this->assertEquals('789', $tokens[2][1]);
        });
        
        $rules = [
            'A'=>[
                [[ ['+','d','d'] ], true],
            ]
        ];
        $parser->init($rules);
        try {
            $parser->parse("123 345 456", function($rule, $tokens){
                
            });
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        
        $parser->parse("123 345 456 678", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(4, $tokens);
        });
    }
    
    function testRuleCallback() {
        $d = 0;
        $cb = function($rule, $tokens) use (&$d) {
            $d = $tokens;
        };
        $tokens = [
            'd'=>'[0-9]+',
        ];
        $rules = [
            'A'  => [
                [['d'], $cb],
            ],
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->parse("123", function($rule, $tokens){
            $this->fail();
        });
        $this->assertCount(1, $d);
        $this->assertEquals('d', $d[0][0]);
        $this->assertEquals('123', $d[0][1]);
    }
    
    function testParseException() {
        $tokens = [
            'd'=>'[0-9]+',
            '+','-','=',
        ];
        $rules = [
            'exp'  => [
                [['d','+','d'], true],
                [['d','-','d'], true],
            ],
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        try {
            $parser->parse("1++", function(){});
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        try {
            $parser->parse("1+", function(){});
            $this->fail();
        } catch (ParseException $e) {
            //
        }

        try {
            $parser->parse("1+1+", function(){});
            $this->fail();
        } catch (ParseException $e) {
            //
        }
        
        $rules = [
            'exp'  => [
                [['exp','+','d'], true],
                [['d','=','exp'], true],
                [['d'], true],
            ],
        ];
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        
        try {
            $parser->parse("1+1=1", function($rule, $items){
            });
            $this->fail();
        } catch (ParseException $e) {
            
        }

    }
    
    function testShiftReduceConflict() {
        $tokens = [
            'd'=>'[0-9]+',
            '+','-','=',
        ];
        $rules = [
            'exp'  => [
                [['d','=', 'exp'], true],
                [['-','exp'], true],
                [['d'], true],
            ],
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->parse("1=-1", function($rule, $items){
        });
    }

    function testTree() {
        $tokens = [
            'd'=>'[0-9]+',
            '{',
            '}',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 'd', 'B' ], true],
                [[ 'd' ], true],
            ],
            'B'=>[
                [['{','A','}'], true]
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setSkipTokens(['sp']);
        $parser->setLexer($lexer);
        $parser->init($rules);
        print_r($parser->tree('1'));
        print_r($parser->tree('1 { 11 { 3 } }'));
    }
    
    
    function testDumpLoad() {
        $tokens = [
            'd'=>'[0-9]+',
            'a',
            'b',
            'c',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ 'd', ['+', ['|','a','b']], 'B' ], true],
            ],
            'B'=>[
                [['d'], true]
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $data = $parser->dump();
        $s = serialize($data);
        $e = var_export($data, true);
        $parser = new Parser();

        $parser->setLexer($lexer);
        $v = eval("return $e;");
        $parser->load($v);
        $parser->tree('123 a a b 1244');

        $parser = new Parser();
        $parser->setLexer($lexer);
        $data = unserialize($s);
        $parser->load($data);
        
        $parser->tree('123 a a b 1244');

        ob_start();
        $parser->printTree('123 a a b 1244');
        $text = ob_get_clean();
        $this->assertTrue(is_string($text));
    }

    protected function getProperty($obj, $name) {
        $ref = new \ReflectionProperty(get_class($obj), $name);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }
}
