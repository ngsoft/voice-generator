<?php

namespace Action;

class ClosureAction extends \Action
{
    public function __construct(private readonly \Closure $callback) {}

    protected function execute(\Request $request): \Response
    {
        $fn = $this->callback;
        return $fn($request);
    }
}
