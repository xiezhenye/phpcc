<?php
namespace phpcc;
include '../src/phpcc.php';

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
        
        $count = $lexer->countGroups('1(2(3(4)*)*)*');
        $this->assertEquals(3, $count);
        
        $count = $lexer->countGroups('[-]?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?');
        $this->assertEquals(3, $count);
        
        $count = $lexer->countGroups('"([^"\\\\]|[\\\\](["\\\\/bfnrt]|u[0-9a-z]{4}))*"');
        $this->assertEquals(2, $count);
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
        $this->assertEquals(4, count($offsets));
        $this->assertEquals(0, $offsets[1]);
        $this->assertEquals(1, $offsets[2]);
        $this->assertEquals(1, $offsets[3]);
        $this->assertEquals(2, $offsets[4]);
        
        $patterns = $this->getProperty($lexer, 'patterns');
        $this->assertEquals(3, count($patterns));
        $this->assertEquals('(([1-9][0-9]*)|((\s+))|(\+))SAm', $patterns[0]);
        $this->assertEquals('(((\s+))|(\+))SAm', $patterns[1]);
        $this->assertEquals('((\+))SAm', $patterns[2]);

        $this->assertTrue($lexer->hasPattern('sp'));
        $this->assertTrue($lexer->hasPattern('+'));
        $this->assertFalse($lexer->hasPattern('xx'));
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

    function testPrefer() {
        $m = [
            'test',
            'name'=>'[a-z]\w+',
            'd'=>'\d+','0',
        ];
        $lexer = new Lexer();
        $lexer->init($m);
        $m = $lexer->match('test', 0);
        $this->assertEquals('test', $m[0]);
        $this->assertEquals('test', $m[1]);

        $m = $lexer->match('test', 0, 'name');
        $this->assertEquals('name', $m[0]);
        $this->assertEquals('test', $m[1]);

        $m = $lexer->match('test', 0, ['name']);
        $this->assertEquals('name', $m[0]);
        $this->assertEquals('test', $m[1]);

        $m = $lexer->match('test', 0, ['test']);
        $this->assertEquals('test', $m[0]);
        $this->assertEquals('test', $m[1]);

        $m = $lexer->match('test', 0, ['test', 'name']);
        $this->assertEquals('test', $m[0]);
        $this->assertEquals('test', $m[1]);

        $m = $lexer->match('0', 0, ['name']);
        $this->assertEquals('0', $m[0]);
        $this->assertEquals('0', $m[1]);
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
        $str = '(123+44)';
        $tokens = $lexer->getAllTokens($str);
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

        $stream = $lexer->getTokenStream('1+2');
        $i = 0;
        foreach ($stream as $k=>$v) {
            $this->assertEquals($i, $k);
            if ($i == 0) {
                $this->assertEquals('d', $v[0]);
            }
            if ($i == 1) {
                $this->assertEquals('+', $v[0]);
            }
            if ($i == 2) {
                $this->assertEquals('d', $v[0]);
            }
            $i++;
        }
        $str = '123';
        $tokens = $lexer->getAllTokens($str);
        $this->assertCount(1, $tokens);
    }
    
    function testLongMatch() {
        $m = [
            'if',
            'while',
            'w'=>'[a-z]\w*',
            'sp'=>'\s+',
            'class',
        ];
        $lexer = new Lexer($m);
        $tokens = $lexer->getAllTokens('while if ifonly iff class classes');
        $this->assertEquals(11, count($tokens));
        $this->assertEquals('while', $tokens[0][1]);
        $this->assertEquals('while', $tokens[0][0]);
        $this->assertEquals('if', $tokens[2][0]);
        $this->assertEquals('w', $tokens[4][0]);
        $this->assertEquals('ifonly', $tokens[4][1]);
        $this->assertEquals('w', $tokens[6][0]);
        $this->assertEquals('iff', $tokens[6][1]);
        $this->assertEquals('class', $tokens[8][0]);
        $this->assertEquals('w', $tokens[10][0]);
    }

    function testLine() {
        $m = [
            'w'=>'[a-z]\w*',
            'sp'=>'\s+',
        ];
        $lexer = new Lexer($m);
        $tokens = $lexer->getAllTokens("a\nb c\n\nd");
        $this->assertEquals(7, count($tokens));
        $this->assertEquals(2, $tokens[2][2]);
        $this->assertEquals(0, $tokens[2][3]);
        $this->assertEquals(2, $tokens[4][2]);
        $this->assertEquals(2, $tokens[4][3]);
        $this->assertEquals(4, $tokens[6][2]);
        $this->assertEquals(0, $tokens[6][3]);
    }
    
    function testGroup() {
        $m = [
            'a'=>'a(b+c)+d',
            'b'=>'1(2(3(4)*)*)*',
            'sp'=>'\s+',
        ];
        $lexer = new Lexer($m);
        $tokens = $lexer->getAllTokens('1234 abcd abbcbcd 1 12 123');
    }

    function testExpectToken() {
        $m = [
            '+', '-',
            'd'=>'\d+',
        ];
        $lexer = new Lexer($m);
        $tok = $lexer->expectToken('123', 0, 'd');
        $this->assertNotNull($tok);
        $this->assertEquals('123', $tok[1]);
        $this->assertNotEmpty($lexer->expectToken('12+34', 0, 'd'));
        $this->assertNotEmpty($lexer->expectToken('12+34', 2, '+'));
        $this->assertNotEmpty($lexer->expectToken('12+34', 3, 'd'));
        $this->assertNull($lexer->expectToken('12+34', 3, '+'));
        try {
            $lexer->expectToken('12+34', 0, 'xx');
            $this->fail();
        } catch (\Exception $e) {
            //
        }
        $stream = $lexer->getTokenStream('1+2');
        $stream->next();
        $tok = $stream->expectToken('+');
        $this->assertEquals('+', $tok[0]);
        $this->assertEquals('+', $tok[1]);
        $this->assertEquals(1, $tok[2]);
        $this->assertEquals(1, $tok[3]);


        $tok = $stream->expectToken('d');
        $this->assertNotEmpty($tok);

        $stream = $lexer->getTokenStream('1+2');
        $tok = $stream->expectToken('d');
        $this->assertNotEmpty($tok);
    }



    function testExpectString() {
        $m = [
            '+', '-',
            'd'=>'\d+',
        ];
        $lexer = new Lexer($m);
        $this->assertFalse($lexer->expectString('123', 0, 'd'));
        $this->assertTrue($lexer->expectString('123', 0, '123'));
        $this->assertTrue($lexer->expectString('12+34', 2, '+'));
        $this->assertTrue($lexer->expectString('12+34', 3, '34'));

        $stream = $lexer->getTokenStream('1+2');
        $stream->next();

        $tok = $stream->expectString('+');
        $this->assertEquals('+', $tok[0]);
        $this->assertEquals('+', $tok[1]);
        $this->assertEquals(1, $tok[2]);
        $this->assertEquals(1, $tok[3]);

        $tok = $stream->expectString('2');
        $this->assertEquals('2', $tok[0]);
        $this->assertEquals('2', $tok[1]);
        $this->assertNotEmpty($tok);

        $stream = $lexer->getTokenStream('1+2');
        $tok = $stream->expectString('1');
        $this->assertEquals('1', $tok[0]);
        $this->assertEquals('1', $tok[1]);
        $this->assertNotEmpty($tok);
    }

    function testPutBack() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'(\s+)',
            '+',
        ];
        $lexer = new Lexer();
        $lexer->init($m);
        $s = $lexer->getTokenStream('1+2');
        $tok = $s->fetch();
        $this->assertEquals('d', $tok[0]);
        $tok = $s->fetch();
        $this->assertEquals('+', $tok[0]);
        $s->putBack($tok);

        $tok = $s->fetch();
        $this->assertEquals('+', $tok[0]);

        $tok = $s->fetch();
        $this->assertEquals('d', $tok[0]);
        $s->putBack($tok);
        $tok = $s->fetch();
        $this->assertEquals('d', $tok[0]);

        $tok = $s->fetch();
        $this->assertNull($tok);
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
            $this->assertEquals(1, $e->getCharLine());
            $this->assertEquals(0, $e->getCharOffset());

        }
        
        try {
            $lexer->lex('12-12', function($name, $value, $line, $offset){});
            $this->fail();
        }catch (LexException $e) {
            $this->assertEquals('-', $e->getChar());
            $this->assertEquals(1, $e->getCharLine());
            $this->assertEquals(2, $e->getCharOffset());
        }
        
    }

    function  testDumpLoad() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'\s+',
            '+','-','*','/','(',')',
        ];
        $lexer = new Lexer($m);
        $d = $lexer->dump();

        $lexer = new Lexer();
        $lexer->load($d);

        $str = '(123+44)';
        $tokens = $lexer->getAllTokens($str);
        $this->assertCount(5, $tokens);
    }

    protected function getProperty($obj, $name) {
        $ref = new \ReflectionProperty(get_class($obj), $name);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }

}
