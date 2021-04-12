import Ip from "./ip";

export interface DriverInterface {
    coroutine(callback, onResolved): any
    tick(interval: number, callback): void
    run(): void;
    stop(): void;
}

export interface RailInterface {
    run(ip: Ip, context: any): void
    pipe(callback): void
}

export interface IpStrategyInterface
{
    push(ip: Ip): void
    pop(): any
    done(ip: Ip): void
}