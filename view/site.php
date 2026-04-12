<?php
extend('vite');
echo include_view('layout/header');
echo $content ?? '';
