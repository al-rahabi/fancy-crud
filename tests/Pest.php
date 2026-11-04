<?php

use FancyCrud\Tests\TestCase;

// Every file under tests/Feature runs on the Testbench app with the fixture CRUD.
pest()->extend(TestCase::class)->in('Feature');
