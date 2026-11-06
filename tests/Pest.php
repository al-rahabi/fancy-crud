<?php

use FancyCrud\Tests\SupportTestCase;
use FancyCrud\Tests\TestCase;

// tests/Feature: Testbench app with the fixture CRUD. tests/Unit: lightweight app with Spatie tables.
pest()->extend(TestCase::class)->in('Feature');
pest()->extend(SupportTestCase::class)->in('Unit');
