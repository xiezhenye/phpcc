<?php

namespace phpcc;

include '../src/Lexer.php';
include '../src/Parser.php';

class ParserTest extends \PHPUnit_Framework_TestCase {
    function setUp() {
        
    }
    
    function testFirst() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $f = $buidler->getFirst('A');
        $this->assertEquals(['d'=>'d','('=>'(',], $f);
        
        $f = $buidler->getFirst('d');
        $this->assertEquals(['d'=>'d'], $f);
        
        $f = $buidler->getFirst('(');
        $this->assertEquals(['('=>'('], $f);
        
        $buidler = new LALR1Builder([
            'A'=>[
                [['d'], true],
                [['B'], true],
              ],
            'B'=>[
                [['f'], true],
              ]
        ]);
        $f = $buidler->getFirst('A');
        $this->assertEquals(['d'=>'d','f'=>'f',], $f);
        $f = $buidler->getFirst('B');
        $this->assertEquals(['f'=>'f',], $f);
    }
    
    function testIsFinal() {
        $buidler = new LALR1Builder([
            'A'=>[
                  [['(','A',')'], true],
                  [['d'], true],
                ]
        ]);
        $this->assertTrue($buidler->isFinal('d'));
        $this->assertTrue($buidler->isFinal('('));
        $this->assertFalse($buidler->isFinal('A'));
    }
    
    function testRoot() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $this->assertEquals('A', $buidler->root());
        
        $buidler = new LALR1Builder([
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            
        ]);
        $this->assertEquals('B', $buidler->root());
    }
    
    function testRuleHash() {
        $buidler = new LALR1Builder([]);
        $rule = ['A',0,0];
        $hash = $buidler->ruleHash($rule);
        $this->assertEquals(md5('["A",0,0]'), $hash);
    }
    
    function testShift() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
        ]);
        $result = $buidler->shiftStateRule(['A',0,0,[''=>'']]);
        $this->assertEquals(4, count($result));
        $this->assertEquals('A', $result[0]);
        $this->assertEquals(0, $result[1]);
        $this->assertEquals(1, $result[2]);
        $this->assertEquals([''=>'',')'=>')'], $result[3]);
    }
    
    function testState() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['d'], true],
              ],
        ]);
        $states = $buidler->build();
        $this->assertEquals(2, count($states));
        $states2 = $buidler->optimize();
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
        $buidler = new LALR1Builder($rules);
        $states = $buidler->build();
        $this->assertEquals(2, count($states));
        $states2 = $buidler->optimize();
        $this->assertEquals(2, count($states2));
        $this->assertEquals(1, $states2[0][2]['d']);
        $this->assertEquals(1, count($states2[0][1]));
        
    }
    
    function testLALRException() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['A','a'], true],
                [['A','a'], true],
            ],
        ]);
        try {
            $buidler->build();
            $buidler->optimize();
            $this->fail();
        } catch (LALR1Exception $e) {
            //$this->assertEquals(LALR1Exception::REDUCE_REDUCE_CONFLICT, $e->getCode());
        }
    }
    
    function testEBNF() {
        $tokens = [
            'd'=>'[0-9]+',
            'sp' => '\s+',
        ];
        $rules = [
            'A'=>[
                [[ ['*','d'] ], true],
            ]
        ];
        $buidler = new LALR1Builder([]);
        $ret = $buidler->EBNF2BNF($rules);
        $this->assertArrayHasKey("A.0.0'", $ret);
        
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
        $parser->parse("123b", function($rule, $tokens){
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
                [[ 's', ['?','d'] ], true],
            ]
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $parser->parse("abc 123", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(2, $tokens);
            $this->assertEquals('s', $tokens[0][0]);
            $this->assertEquals('abc', $tokens[0][1]);
            $this->assertEquals('d', $tokens[1][0]);
            $this->assertEquals('123', $tokens[1][1]);
        });
        
        $parser->parse("abc", function($rule, $tokens){
            $this->assertEquals('A', $rule);
            $this->assertCount(1, $tokens);
            $this->assertEquals('s', $tokens[0][0]);
            $this->assertEquals('abc', $tokens[0][1]);
        });
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
            'd'=>'[0-9]',
            '+'=>'\+',
            '-'=>'-',
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
    }
    
}
