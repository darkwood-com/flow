<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\YFlowData;
use Flow\Job\LambdaJob;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};

printf("Use %s\n", $driver::class);

// from https://github.com/loophp/combinator?tab=readme-ov-file#available-combinators

$handle = static function (string $expression, array $params) {
    $job = new LambdaJob($expression);
    $result = array_reduce($params, static fn ($carry, $param) => empty($carry) ? $job($param) : $carry($param));
    $encodedParams = array_map(static fn ($param) => $param instanceof Closure ? 'Closure' : (is_object($param) ? get_class($param) : $param), $params);
    echo 'Evaluating ' . $expression . ' with params ' . json_encode($encodedParams) . ' : ' . $result . "\n";
};

// | A         | Apply         | `SK(SK)`          | `$`     | `λab.ab`                    | `a => b => a(b)`                         | `(a -> b) -> a -> b`                                 | 2           |
$handle('λa.λb.a(b)', [
    static fn ($a) => $a,
    8,
]);

// | B         | Bluebird      | `S(KS)K`          | `.`     | `λabc.a(bc)`                | `a => b => c => a(b(c))`                 | `(a -> b) -> (c -> a) -> c -> b`                     | 3           |
$handle('λa.λb.λc.a(b c)', [
    static fn ($a) => $a,
    static fn ($b) => $b,
    7,
]);

// | Blackbird | Blackbird     | `BBB`             | `...`   | `λabcd.a(bcd)`              | `a => b => c => => d => a(b(c)(d))`      | `(c -> d) -> (a -> b -> c) -> a -> b -> d`           | 4           |

// | C         | Cardinal      | `S(BBS)(KK)`      | `flip`  | `λabc.acb`                  | `a => b => c => a(c)(b)`                 | `(a -> b -> c) -> b -> a -> c`                       | 3           |

// | D         | Dove          | `BB`              |         | `λabcd.ab(cd)`              | `a => b => c => d => a(b)(c(d))`         | `(a -> c -> d) -> a -> (b -> c) -> b -> d`           | 4           |

// | E         | Eagle         | `B(BBB)`          |         | `λabcde.ab(cde)`            | `a => b => c => d => e => a(b)(c(d)(e))` | `(a -> d -> e) -> a -> (b -> c -> d) -> b -> c -> e` | 5           |

// | F         | Finch         | `ETTET`           |         | `λabc.cba`                  | `a => b => c => c(b)(a)`                 | `a -> b -> (b -> a -> c) -> c`                       | 3           |

// | G         | Goldfinch     | `BBC`             |         | `λabcd.ad(bc)`              | `a => b => c => d => a(d)(b(c))`         | `(a -> b -> c) -> (d -> b) -> d -> a -> c`           | 4           |

// | H         | Hummingbird   | `BW(BC)`          |         | `λabc.abcb`                 | `a => b => c => a(b)(c)(b)`              | `(a -> b -> a -> c) -> a -> b -> c`                  | 3           |

// | I         | Idiot         | `SKK`             | `id`    | `λa.a`                      | `a => a`                                 | `a -> a`                                             | 1           |
$handle('λa.a', [
    static fn ($a) => $a,
    6,
]);

// | J         | Jay           | `B(BC)(W(BC(E)))` |         | `λabcd.ab(adc)`             | `a => b => c => d => a(b)(a(d)(c))`      | `(a -> b -> b) -> a -> b -> a -> b`                  | 4           |

// | K         | Kestrel       | `K`               | `const` | `λab.a`                     | `a => b => a`                            | `a -> b -> a`                                        | 2           |
$handle('λa.λb.a', [
    5,
    6,
]);

// | Ki        | Kite          | `KI`              |         | `λab.b`                     | `a => b => b`                            | `a -> b -> b`                                        | 2           |
$handle('λa.λb.b', [
    5,
    6,
]);

// | L         | Lark          | `CBM`             |         | `λab.a(bb)`                 | `a => b => a(b(b))`                      |                                                      | 2           |

// | M         | Mockingbird   | `SII`             |         | `λa.aa`                     | `a => a(a)`                              |                                                      | 1           |

// | O         | Owl           | `SI`              |         | `λab.b(ab)`                 | `a => b => b(a(b))`                      | `((a -> b) -> a) -> (a -> b) -> b`                   | 2           |

// | Omega     | Ω             | `MM`              |         | `λa.(aa)(aa)`               | `a => (a(a))(a(a))`                      |                                                      | 1           |

// | Phoenix   |               |                   |         | `λabcd.a(bd)(cd)`           | `a => b => c => d => a(b(d))(c(d))`      | `(a -> b -> c) -> (d -> a) -> (d -> b) -> d -> c`    | 4           |

// | Psi       |               |                   | `on`    | `λabcd.a(bc)(bd)`           | `a => b => c => d => a(b(c))(b(d))`      | `(a -> a -> b) -> (c -> a) -> c -> c -> b`           | 4           |

// | Q         | Queer         | `CB`              | `(##)`  | `λabc.b(ac)`                | `a => b => c => b(a(c))`                 | `(a -> b) -> (b -> c) -> a -> c`                     | 3           |

// | R         | Robin         | `BBT`             |         | `λabc.bca`                  | `a => b => c => b(c)(a)`                 | `a -> (b -> a -> c) -> b -> c`                       | 3           |

// | S         | Starling      | `S`               | `<*>`   | `λabc.ac(bc)`               | `a => b => c => a(c)(b(c))`              | `(a -> b -> c) -> (a -> b) -> a -> c`                | 3           |

// | S\_       |               |                   | `<*>`   | `λabc.a(bc)c`               | `a => b => c => a(b(c))(c)`              | `(a -> b -> c) -> (b -> a) -> b -> c`                | 3           |

// | S2        |               |                   | `<*>`   | `λabcd.a((bd)(cd))`         | `a => b => c => d => a(b(d))(c(d))`      | `(b -> c -> d) -> (a -> b) -> (a -> c) -> a -> d`    | 4           |

// | T         | Thrush        | `CI`              | `(&)`   | `λab.ba`                    | `a => b => b(a)`                         | `a -> (a -> b) -> b`                                 | 2           |

// | U         | Turing        | `LO`              |         | `λab.b(aab)`                | `a => b => b(a(a)(b))`                   |                                                      | 2           |

// | V         | Vireo         | `BCT`             |         | `λabc.cab`                  | `a => b => c => c(a)(b)`                 | `a -> b -> (a -> b -> c) -> c`                       | 3           |

// | W         | Warbler       | `C(BMR)`          |         | `λab.abb`                   | `a => b => a(b)(b)`                      | `(a -> a -> b) -> a -> b`                            | 2           |

// | Y         | Y-Fixed point |                   |         | `λa.(λb(a(bb))(λb(a(bb))))` | `a => (b => b(b))(b => a(c => b(b)(c)))` |                                                      | 1           |
$handle('λf.(λx.f (λy.(x x) y)) (λx.f (λy.(x x) y))', [
    static function ($factorial) {
        return static function (YFlowData $data) use ($factorial): YFlowData {
            return new YFlowData(
                $data->id,
                $data->number,
                ($data->number <= 1) ? 1 : $data->number * $factorial(new YFlowData($data->id, $data->number - 1, $data->result - 1))->result
            );
        };
    },
    new YFlowData(1, 6),
]);

// | Z         | Z-Fixed point |                   |         | `λa.M(λb(a(Mb)))`           |                                          |                                                      | 1           |

// $job = new \Flow\Job\LambdaJob('λa.λb.(a b)', fn($a) => $a);
// $job = new \Flow\Job\LambdaJob('λa.λb.λc.(a (b c))', fn($a) => $a);
// $job = new \Flow\Job\LambdaJob('λa.λb.λc.λd.(a (b (c d)))', fn($a) => $a);

// $job = new \Flow\Job\LambdaJob('λf.(λx.f (x x)) (λx.f (x x))');
// $job = new \Flow\Job\LambdaJob('λab.a(b)');
// $job = new \Flow\Job\LambdaJob('λabcd.a(b(c(d)))');

// $job = new \Flow\Job\LambdaJob('(λy.(λu.y λx.(u u)) ((λx.λu.x (λy.y (x z))) λu.((x x) λu.y)))', $factorialYJob);
// $job = new \Flow\Job\LambdaJob('(λf.(λx.f (x x)) (λx.f (x x)))', $factorialYJob);

// $job = new \Flow\Job\LambdaJob('(λa.(λb.(a (b b))) (λb.(a (b b))))', $factorialYJob);
