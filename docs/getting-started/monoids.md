# Monoids

According to [Wikipedia](https://en.wikipedia.org/wiki/Monoid), Monoid is an abstract algebra that get its usage in function composition.

## Rail implementation

We consider `Rails` as a set of elements in our ensemble as a Monoid implementation.  
By using `pipe` as a binary operation, `Rails` can be composed together with others `Rail` element.  
A `Rail` can process one or many `Ips` which has its application for asynchronous programming when mixing with [`Drivers`](drivers.md).

## Rail

This is the standard Rail implementation that support asynchronous `Ip` processing.

## RailDecorator

This is useful for implementing the [decorator design pattern](https://en.wikipedia.org/wiki/Decorator_pattern).

## Make your own Rail

You can make your custom Rail by implementing `RFBP\RailInterface`