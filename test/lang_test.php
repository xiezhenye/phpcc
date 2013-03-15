<?php
include '../sample/lang.php';

class LangTest extends \PHPUnit_Framework_TestCase {
    function __construct() {
        $this->lang = new \Lang();
    }
    function testVar() {
        $code = "a=5\na\n";
        $result = $this->lang->execute($code);
        $this->assertEquals(5, $result);
    }

    function testExp() {
        $code = <<<'EOF'
a=6
b=(2+a*2)/2

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(7, $result);

        $code = <<<'EOF'
a=3
b=4
c=sqrt(a*a+b*b)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(5, $result);

        $code = <<<'EOF'
1+5>5

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(1, $result);

        $code = <<<'EOF'
4*4<=16

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(1, $result);

        $code = <<<'EOF'
2*2!=2+(5%3)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(0, $result);

        $code = <<<'EOF'
(1==1)+(4!=3)+(3>=1)+(2<=2)+(2<3)+(10>9)+(!0)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(7, $result);
    }

    function testFunc() {
        $code = <<<'EOF'
func foo(a) {
    a*a
}
foo(5)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(25, $result);
        $code = <<<'EOF'
func test(x,y) {
    y*y+x*x
}
test(3,4)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(25, $result);
    }

    function testRecursive() {
        $code = <<<'EOF'
func fac(n) {
    if ( n == 0 , 1,  n * fac(n-1) )
}
fac(5)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(120, $result);
    }

    function testLoop() {
        $code = <<<'EOF'
i=1
t=0
while(i<=100, {
    t=t+i
    i=i+1
})
t

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(5050, $result);
    }

    function testQuote() {
        $code = <<<'EOF'
func for(`a,`b,`c, `stm) {
    ~a
    while (~b, {
        ~stm
        ~c
    })
}
t=0
for ( j=1, j<=100, j=j+1, {
    t=t+j
})
t

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(5050, $result);
    }

    function testFuncArg() {
        $code = <<<'EOF'
func foo(a) {
    a*2
}

func d(f, a) {
    f(f(a))
}
d(foo, 3)

EOF;
        $result = $this->lang->execute($code);
        $this->assertEquals(12, $result);
    }

/*


*/

}

