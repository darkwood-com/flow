# Ip Strategy

When processing Rail with one or multiple Ips, you can choose a strategy that will sequence the order of processing Ip.

## LinearIpStrategy

This process Ip by order : first in, first out.

## StackIpStrategy

This process Ip as a stack order : push ip to the top of the stack, then order ip retrieval from the top stack to bottom.

## MaxIpStrategy

This process Ip as soon less Ip are currently process than the current max.  
You can embed it by a custom strategy with is `LinerarIpStrategy` by default.

## Make your Ip Strategy

You can make your custom Ip strategy by implementing `RFBP\IpStrategyInterface`