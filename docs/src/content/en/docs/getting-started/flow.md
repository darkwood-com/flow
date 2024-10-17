---
title: "Flow"
description: "Flow."
lead: "Flow."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 20
toc: true
---

# Flow

## Flow implementation

According to [Wikipedia](https://en.wikipedia.org/wiki/Monad_(functional_programming)), Monads is an abstract generic structure that get its usage in function composition. Monads can shortly considered as `Programming with effects`.

We consider `Flows` as a set of elements in our ensemble as a Monad implementation :  
- By using `job` as basic function type within the `Flow`.  
- By using `fn` as a binary operation, `Flow` can be composed together with others `Flow` element.  

A `Flow` can process one or many `Ips` which has its application for asynchronous programming when mixing with [`Drivers`](drivers.md).

## Flow

This is the standard Flow implementation that support asynchronous `Ip` processing.

## FlowDecorator

This is useful for implementing the [decorator design pattern](https://en.wikipedia.org/wiki/Decorator_pattern).

## TransportFlow

TransportFlow will interact with Flow with Producer and Sender.

## YFlow

YFlow use YCombinator to provide recursion.

## Make your own Flow

You can make your custom Flow by implementing `Flow\FlowInterface`.
