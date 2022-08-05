---
id: transform
title: Transforms
sidebar_label: Transforms
---

The fbt comes with 2 transforms.

## FbtTransform
The first is the `FbtTransform`.  Internally, it first transforms `<fbt>` instances into their `fbt(...)` equivalent.  After which, it turns all `fbt(...)` calls into `fbt::_(...)` calls with an intermediary payload as the first argument, and the runtime arguments to be passed in.

## FbtRuntimeTransform
This transform takes the intermediary payload and turns it into the object that the `fbt::_(...)` runtime expects.
