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
- By using `pipe` as a binary operation, `Flow` can be composed together with others `Flow` element.  

A `Flow` can process one or many `Ips` which has its application for asynchronous programming when mixing with [`Drivers`](drivers.md).

## Flow

This is the standard Flow implementation that support asynchronous `Ip` processing.

## FlowDecorator

This is useful for implementing the [decorator design pattern](https://en.wikipedia.org/wiki/Decorator_pattern).

## ErrorFlow

ErrorFlow catch any exception from rail and will be handled by its own job.

## SequenceFlow

SequenceFlow will sequence in order the processing IPs from an array of Flow.

## ParallelFlow

ParallelFlow will sequence processing IPs to parallels array of jobs.

## Make your own Flow

You can make your custom Flow by implementing `Flow\FlowInterface`