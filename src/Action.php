<?php

abstract class Action
{
    final public function __invoke(Request $request): Response
    {
        return $this->execute($request);
    }

    abstract protected function execute(Request $request): Response;
}
