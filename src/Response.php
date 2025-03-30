<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */
interface Response extends Stringable
{
    public function render(): never;

    public function getContent(): string;
}
