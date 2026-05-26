---
title: "Async Handler"
description: "Async Handler."
lead: "Async Handler."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 35
toc: true
---

# Async Handler

When processing Flow at async step, you can choose a handler that will process asynchronously the Ip.

## AsyncHandler

This is the default one. Ip is async processed immediately.

## BatchAsyncHandler

This async process Ip as batch capability : the handler will wait for a certain amount of async messages ($batchSize) to be processed before pushing them.

## DeferAsyncHandler

This async process Ip to offer defer capability : the handler will pass [$data, $defer] as entry for the job. In that case, the job can fine control the async process. $defer is a callable that embark two callbacks
- an complete callback to store result
- an async callback to go to the next async call.

## Make your Async Handler

You can make your custom Ip strategy by implementing `Flow\AsyncHandlerInterface`
