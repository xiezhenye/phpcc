<?php
namespace phpcc;
include '../src/Lexer.php';

ini_set('output_buffering', 0);

class LexerTest extends \PHPUnit_Framework_TestCase {
    function setUp() {
        
    }
    
    function testCountGroups() {
        $lexer = new Lexer();
        $count = $lexer->countGroups('(');
        $this->assertEquals(1, $count);
        
        $count = $lexer->countGroups('(aa)((bb)gg)');
        $this->assertEquals(3, $count);
        
        $count = $lexer->countGroups('\\(');
        $this->assertEquals(0, $count);
        
        $count = $lexer->countGroups('\\\\(');
        $this->assertEquals(1, $count);
        
        $count = $lexer->countGroups('(\\\\()');
        $this->assertEquals(2, $count);
        
        $count = $lexer->countGroups('\\(()');
        $this->assertEquals(1, $count);
        
        $count = $lexer->countGroups('(?:aaa)');
        $this->assertEquals(0, $count);
        
        $count = $lexer->countGroups('aaa(?!bbb)(ccc)+');
        $this->assertEquals(1, $count);
    }
    
    function testInit() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'(\s+)',
            '+',
        ];
        $lexer = new Lexer($m);
        $names = $this->getProperty($lexer, 'names');
        $this->assertEquals(3, count($names));
        $this->assertEquals('d', $names[0]);
        $this->assertEquals('+', $names[2]);
        
        $offsets = $this->getProperty($lexer, 'groupOffsets');
        $this->assertEquals(3, count($offsets));
        $this->assertEquals(0, $offsets[1]);
        $this->assertEquals(1, $offsets[3]);
        $this->assertEquals(2, $offsets[4]);
        
        $patterns = $this->getProperty($lexer, 'patterns');
        $this->assertEquals(3, count($patterns));
        $this->assertEquals('(([1-9][0-9]*)|((\s+))|(\+))SAm', $patterns[0]);
        $this->assertEquals('(((\s+))|(\+))SAm', $patterns[1]);
        $this->assertEquals('((\+))SAm', $patterns[2]);
    }
    
    function testMatch() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'(\s+)',
            '+'=>'\+',
        ];
        $lexer = new Lexer();
        $lexer->init($m);
        $m = $lexer->match('+', 0);
        $this->assertEquals('+', $m[0]);
        $this->assertEquals('+', $m[1]);
    }
    
    function testLex() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'\s+',
            '+'=>'\+',
            '-'=>'-',
            '*'=>'\*',
            '/'=>'\/',
            '('=>'\(',
            ')'=>'\)',
        ];
        $lexer = new Lexer($m);
        
        $tokens = $lexer->getAllTokens('(123+44)');
        $this->assertEquals(5, count($tokens));
        $this->assertEquals('(', $tokens[0][0]);
        $this->assertEquals('(', $tokens[0][1]);
        $this->assertEquals('d', $tokens[1][0]);
        $this->assertEquals('123', $tokens[1][1]);
        $this->assertEquals(1, $tokens[1][2]);
        $this->assertEquals(1, $tokens[1][3]);
        $this->assertEquals(')', $tokens[4][0]);
        $this->assertEquals(')', $tokens[4][1]);
        $this->assertEquals(1, $tokens[4][2]);
        $this->assertEquals(7, $tokens[4][3]);

    }
    
    function testLongMatch() {
        $m = [
            'if'=>'if',
            'while'=>'while',
            'w'=>'[a-z]\w*',
            'sp'=>'\s+',
        ];
        $lexer = new Lexer($m);
        $tokens = $lexer->getAllTokens('while if whiler iff');
        $this->assertEquals(7, count($tokens));
        $this->assertEquals('while', $tokens[0][0]);
        $this->assertEquals('if', $tokens[2][0]);
        $this->assertEquals('w', $tokens[4][0]);
        $this->assertEquals('whiler', $tokens[4][1]);
        $this->assertEquals('w', $tokens[6][0]);
        $this->assertEquals('iff', $tokens[6][1]);
    }
    
    function testException() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'(\s+)',
            '+'=>'\+',
        ];
        $lexer = new Lexer();
        $lexer->init($m);
        //$this->setExpectedException('phpcc\\');
        try {
            $lexer->lex('-', function($name, $value, $line, $offset){});
            $this->fail();
        }catch (LexException $e) {
            $this->assertEquals('-', $e->getChar());
        }
        
        try {
            $lexer->lex('12-12', function($name, $value, $line, $offset){});
            $this->fail();
        }catch (LexException $e) {
            $this->assertEquals('-', $e->getChar());
        }
        
    }
    
    protected function getProperty($obj, $name) {
        $ref = new \ReflectionProperty(get_class($obj), $name);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }
    
}
