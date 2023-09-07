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

## Make your Async Handler

You can make your custom Ip strategy by implementing `Flow\AsyncHandlerInterface`
