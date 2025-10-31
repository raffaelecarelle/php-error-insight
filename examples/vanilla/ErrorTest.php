<?php
require_once __DIR__ . '/ErrorTest2.php';
class ErrorTest
{
    public static function throw()
    {
        ErrorTest2::throw();
    }
}