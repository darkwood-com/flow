---
title : "Flow"
description: "Assemble your code by adopting asynchronous as native implementation and build with functional programming and monads."
lead: "Assemble your code by adopting asynchronous as native implementation and build with functional programming and monads."
date: 2020-10-06T08:47:36+00:00
lastmod: 2020-10-06T08:47:36+00:00
draft: false
images: []
---

## Usage

```php
<?php

use Flow\Flow\Flow;
use Flow\FlowFactory;
use Flow\Ip;

class D1 {
    public function __construct(public int $n1) {}
}

class D2 {
    public function __construct(public int $n2) {}
}

class D3 {
    public function __construct(public int $n3) {}
}

class D4 {
    public function __construct(public int $n4) {}
}

$flow = (new FlowFactory())->create(static function() {
    yield fn (D1 $data1) => new D2($data1->n1 += 1);
    yield fn (D2 $data2) => new D3($data2->n2 * 2);
    yield function(D3 $data3) {
        printf("my number %d\n", $ip->data->n3)); // display 'my number 10'

        return new D4($data3->n3);
    };
});

$ip = new Ip(new D1(4));
$flow($ip);
$flow->await();
```
