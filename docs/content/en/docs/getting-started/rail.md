---
title: "Rail"
description: "Rail."
lead: "Rail."
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

# Rail

## Rail implementation

According to [Wikipedia](https://en.wikipedia.org/wiki/Monad_(functional_programming)), Monads is an abstract generic structure that get its usage in function composition. Monads can shortly considered as `Programming with effects`.

We consider `Rails` as a set of elements in our ensemble as a Monad implementation :  
- By using `job` as basic function type within the `Rail`.  
- By using `pipe` as a binary operation, `Rail` can be composed together with others `Rail` element.  

A `Rail` can process one or many `Ips` which has its application for asynchronous programming when mixing with [`Drivers`](drivers.md).

## Rail

This is the standard Rail implementation that support asynchronous `Ip` processing.

## RailDecorator

This is useful for implementing the [decorator design pattern](https://en.wikipedia.org/wiki/Decorator_pattern).

## ErrorRail

ErrorRail catch any exception from rail and will be handled by its own job.

## SequenceRail

SequenceRail will sequence in order the processing IPs from an array of Rail.

## ParallelRail

ParallelRail will sequence processing IPs to parallels array of jobs.

## Make your own Rail

You can make your custom Rail by implementing `RFBP\RailInterface`