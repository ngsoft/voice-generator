<?php

namespace Interfaces;

use Symfony\Component\HttpFoundation\Request;

interface ActionInterface
{
    public function execute(Request $request): \ResponseView;
}
