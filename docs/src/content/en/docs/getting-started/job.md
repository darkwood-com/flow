---
title: "Job"
description: "Job."
lead: "Job."
date: 2020-10-13T15:21:01+02:00
lastmod: 2020-10-13T15:21:01+02:00
draft: false
images: []
menu:
  docs:
    parent: "getting-started"
weight: 10
toc: true
---

# Job

When using Flow, you can pass Closure or JobInterface, it's useful when you want to specialize your Job, that come with dependecy injection.

## ClosureJob

ClosureJob simplifies job handling by allowing the use of closures or custom job classes, providing a versatile solution for managing jobs in your application.

## YJob

The YJob class defines the Y combinator to recursively apply the job function, making it particularly useful in scenarios where you need to perform recursive tasks without explicitly writing recursive functions.

## Make your own Job

You can make your custom Job by implementing `Flow\JobInterface`.
